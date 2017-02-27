<?php

namespace Drupal\Tests\viewfield\Kernel;

use Drupal\comment\Entity\Comment;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\viewfield\Tests\ViewfieldTestTrait;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;


/**
 * Tests the new entity API for the viewfield field type.
 *
 * @group entity_reference
 */
class ViewfieldItemTest extends FieldKernelTestBase {

  use ViewfieldTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment', 'file', 'taxonomy', 'text', 'filter', 'views', 'field', 'viewfield', 'viewfield_test_views'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_nid_argument_blocks', 'test_nid_argument_blocks_not', 'test_node_view');

  protected $nodes;

  /**
   * The taxonomy vocabulary to test with.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The taxonomy term to test with.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * The test entity with a string ID.
   *
   * @var \Drupal\entity_test\Entity\EntityTestStringId
   */
  protected $entityStringId;

  /**
   * Sets up the test.
   */
  protected function setUp() {
    parent::setUp();

//    $this->installEntitySchema('entity_test_string_id');
//    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
//    $this->installEntitySchema('comment');
//    $this->installEntitySchema('file');
//
//    $this->installSchema('comment', ['comment_entity_statistics']);
//    $this->installSchema('node', ['node_access']);

    ViewTestData::createTestViews(get_class($this), array('viewfield_test_views'));

    $this->nodes = [];
    for ($k = 0; $k < 10; $k++) {
      $node = Node::create(['type' => 'default', 'title' => $this->randomMachineName()]);
      $node->save();
      $this->nodes[] = $node;
    }

    // Use the util to create an instance.
    $this->createViewfield('entity_test', 'entity_test', 'field_test_viewfield', 'Test viewfield');
//    $this->createViewfield('entity_test', 'entity_test', 'field_test_viewfield_multiple', 'Test viewfield multiple values', 0, [], ['block' => 'block'], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
  }

  /**
   * Tests the viewfield type for referencing content entities.
   */
  public function testContentViewfieldItem() {
    $target_id = 'test_nid_argument_blocks';
    $display_id = 'test_nid_argument_blocks_1';
    $arguments = serialize([$this->nodes[0]->id()]);

    $view = Views::getView($target_id);
    $view->setDisplay($display_id);

    // Just being able to create the entity like this verifies a lot of code.
    $entity = EntityTest::create();
    $entity->field_test_viewfield->target_id = $target_id;
    $entity->field_test_viewfield->display_id = $display_id;
    $entity->field_test_viewfield->arguments = $arguments;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertTrue($entity->field_test_viewfield instanceof FieldItemListInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_test_viewfield[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEquals($entity->field_test_viewfield->target_id, $target_id);
    $this->assertEquals($entity->field_test_viewfield->display_id, $display_id);
    $this->assertEquals($entity->field_test_viewfield->arguments, $arguments);
    $this->assertEquals($entity->field_test_viewfield->entity->label(), $view->storage->label());
    $this->assertEquals($entity->field_test_viewfield->entity->id(), $view->storage->id());
    $this->assertEquals($entity->field_test_viewfield->entity->uuid(), $view->storage->uuid());
    // Verify that the label for the target ID property definition is correct.
    $label = $entity->field_test_viewfield->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('target_id')
      ->getLabel();
    $this->assertTrue($label instanceof TranslatableMarkup);
    $this->assertEquals($label->render(), 'View ID');
    // Verify that the label for the target ID property definition is correct.
    $label = $entity->field_test_viewfield->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('display_id')
      ->getLabel();
    $this->assertTrue($label instanceof TranslatableMarkup);
    $this->assertEquals($label->render(), 'Display ID');
    // Verify that the label for the target ID property definition is correct.
    $label = $entity->field_test_viewfield->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('arguments')
      ->getLabel();
    $this->assertTrue($label instanceof TranslatableMarkup);
    $this->assertEquals($label->render(), 'Arguments');

    // Test all the possible ways of assigning a value.
    $entity->field_test_viewfield->target_id = $target_id;
    $entity->field_test_viewfield->display_id = $display_id;
    $entity->field_test_viewfield->arguments = $arguments;
    $this->assertEquals($entity->field_test_viewfield->entity->id(), $view->storage->id());
    $this->assertEquals($entity->field_test_viewfield->entity->label(), $view->storage->label());

    $entity->field_test_viewfield = [
      'target_id' => $target_id,
      'display_id' => $display_id,
      'arguments' => $arguments
    ];
    $this->assertEquals($entity->field_test_viewfield->entity->id(), $view->storage->id());
    $this->assertEquals($entity->field_test_viewfield->entity->label(), $view->storage->label());

    // Test assigning an invalid item throws an exception.
    try {
      $entity->field_test_viewfield = [
        'target_id' => 'invalid',
        'entity' => $view->storage
      ];
      $this->fail('Assigning an invalid item throws an exception.');
    } catch (\InvalidArgumentException $e) {
      $this->assertTrue(TRUE, 'Assigning an invalid item throws an exception.');
    }
    try {
      $entity->field_test_viewfield = [
        'target_id' => $target_id,
        'display_id' => 'invalid'
      ];
      $this->fail('Assigning an invalid display_id throws an exception.');
    } catch (\InvalidArgumentException $e) {
      $this->assertTrue(TRUE, 'Assigning an display_id item throws an exception.');
    }
    // Test assigning a disabled view throws an exception.
    $view->storage->disable();
    $view->save();
    try {
      $entity->field_test_viewfield = [
        'target_id' => $target_id,
        'display_id' => $display_id
      ];
      $this->fail('Assigning a disabled item throws an exception.');
    } catch (\InvalidArgumentException $e) {
      $this->assertTrue(TRUE, 'Assigning a disabled item throws an exception.');
    }
  }

}
