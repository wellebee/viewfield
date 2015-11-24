<?php

/**
 * @file
 * Contains Drupal\viewfield\Tests\ViewFieldTest.
 */

namespace Drupal\viewfield\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\simpletest\WebTestBase;

/**
 * Tests viewfield field.
 *
 * @group viewfield
 */
class ViewFieldTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'viewfield', 'field_ui'];

  /**
   * A field to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The instance used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  protected function setUp() {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test field creation and attachment to an article.
   */
  function testFieldCreation() {
    $field_name = Unicode::strtolower($this->randomMachineName());
    // Create a field with settings to validate.
    $this->fieldStorage = entity_create('field_storage_config', array(
      'field_name' => $field_name,
      'entity_type' => 'node',
      'translatable' => FALSE,
      'type' => 'viewfield',
      'cardinality' => '1',
    ));
    $this->fieldStorage->save();
    $this->field = entity_create('field_config', array(
      'field_storage' => $this->fieldStorage,
      'bundle' => 'article',
      'title' => DRUPAL_DISABLED,
    ));
    $this->field->save();
    entity_get_form_display('node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'viewfield_select',
        'settings' => array(),
      ))
      ->save();
    entity_get_display('node', 'article', 'full')
      ->setComponent($field_name, array(
        'type' => 'viewfield_default',
      ))
      ->save();

    $this->assertDefaultViewSelected($field_name);
    $this->assertDefaultViewRequired($field_name);
    $this->assertNodeCreated($field_name);
    $this->assertViewDisplays($field_name);
  }

  protected function assertNodeCreated($field_name) {
    // Display article creation form.
    $this->drupalGet('node/add/article');
    $view_select_name = "{$field_name}[0][vname]";
    $this->assertFieldByName($view_select_name, NULL,'Views select list is displayed.');
    $this->assertFieldByName("{$field_name}[0][vargs]", '' ,
      'Views arguments text field is displayed');

    $edit = array (
      "title[0][value]" => 'Test',
      $view_select_name => 'user_admin_people|default',
    );
    // create article with viewfield
    $this->drupalPostForm(NULL, $edit, t('Save and publish'));
    $this->assertText(t('Article Test has been created.'));
  }

  /**
   * Assert that the view is displayed on a node.
   *
   * @param string $field_name
   *   The field to test
   */
  protected function assertViewDisplays($field_name) {
    // create article
    $this->drupalGet('node/add/article');
    $view_select_name = "{$field_name}[0][vname]";
    $edit = array (
      "title[0][value]" => 'Test1',
      $view_select_name => 'user_admin_people|default',
    );
    $this->drupalPostForm(NULL, $edit, t('Save and publish'));
    
    // test that the view displays on the node
    $elements = $this->xpath("//div[contains(@class,:class) and contains(@class,:class1)]",
      array(':class' => 'view-user-admin-people',':class1' => 'view-display-id-default'));
    $this->assertTrue(!empty($elements), 'Node contains the correct view and display.');
    $elements = $this->xpath("//a[@href=:href]",array(':href' => '/user/1'));
    $this->assertTrue(!empty($elements), 'View is displaying the content.');
  }

  /**
   * Assert that a default view is required when default value checkbox is checked.
   *
   * @param string $field_name
   *   The field to test
   */
  protected function assertDefaultViewRequired($field_name) {
    $this->drupalGet("admin/structure/types/manage/article/fields/node.article.{$field_name}");
    $default_chk_name = 'field[settings][force_default]';
    $this->assertFieldByName($default_chk_name, NULL,'Default value checkbox displayed');
    $edit = array (
      $default_chk_name => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save settings'));
    $this->assertText('Always use default value requires a default value');
  }

  /**
   * Assert that the default view is selected on the node add form.
   *
   * @param string $field_name
   *   The field to test
   */
  protected function assertDefaultViewSelected($field_name) {
    $this->drupalGet("admin/structure/types/manage/article/fields/node.article.{$field_name}");
    $default_view_select_name = "default_value_input[{$field_name}][0][vname]";
    $this->assertFieldByName($default_view_select_name, NULL,'Default view select list is displayed');
    $edit = array (
      $default_view_select_name => 'user_admin_people|default',
    );
    $this->drupalPostForm(NULL, $edit, t('Save settings'));
    $this->assertText("Saved {$field_name} configuration");

    // check that the view is preselected on the node form
    $this->drupalGet('node/add/article');
    $view_select_name = "{$field_name}[0][vname]";

    $this->assertFieldByName($view_select_name, 'user_admin_people|default','Views select list is displayed with correct value');

    // return the default value to its original state
    $this->drupalGet("admin/structure/types/manage/article/fields/node.article.{$field_name}");
    $edit = array (
      $default_view_select_name => '0',
    );
    $this->drupalPostForm(NULL, $edit, t('Save settings'));

  }
}
