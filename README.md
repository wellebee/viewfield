### About

The viewfield module defines an entity reference field type to display a view. Viewfield enables an administrator or content author to integrate a views display into any fieldable entity, such as content, users or paragraphs. In addition, through the use of the **Always use default value** setting, the same view may automatically be placed into all entities in a bundle. Viewfield has considerable theming support, making it easy to customize presentation.

### Field Configuration

To add a Viewfield to an fieldable entity type (e.g., a content type):

1. Edit the content type and navigate to the **Manage Fields** tab.
2. Choose **+ Add Field** and select **Viewfield** under the **Reference** category.
3. Choose the number of field values (distinct view displays) you want to store and press **Save Field Settings**.
4. Next come the field settings on the field edit tab.
5. Selecting **Required field** constrains the field to always contain a value.
6. Assign one or more default values to be used for newly created content, or for use with the **Always use default value** option.
7. Selecting **Always use default value** means the Viewfield will always use the provided default value(s) when rendering the field, and this field will be hidden in all content editing forms, making it unnecessary to assign values individually to each piece of content. Nice! If this is checked, you **must** provide a default value.
8. The set of allowed views and allowed display types may be restricted on entity edit forms using the **Allowed views** and **Allowed display types** checkboxes respectively.
9. Press **Save settings** to complete the field creation process.

### Assigning Field Values
Assigning a value (or default value) to a Viewfield consists of selecting a View and Display from the select boxes, and providing an optional comma-delimited list of arguments (contextual filters) for the display. The argument list may contain tokens. Token help is available by clicking on the **Browse available tokens** link shown below the arguments field.

### Output and Theming
Viewfield has extensive theming support. The default formatter supports the following settings:

- **View title**
Options to render the view display title in the output. Choose from *Above*, *Inline*, *Hidden*, *Visually Hidden*.
- **Always build output**
Produce rendered output even if the view produces no results.
This option may be useful for some specialized cases, e.g., to force rendering of an attachment display even if there are no view results.
- **Empty view title**
Options to render the view display title even when the view produces no results. Choose from *Above*, *Inline*, *Hidden*, *Visually Hidden*. This option has an effect only when **Always build output** is selected.

Viewfield provides default theming with the `viewfield.html.twig` and `viewfield-item.html.twig` templates, which may be overridden. Enable Twig debugging to view file name suggestions in the rendered HTML.

The Viewfield templates employ the core field CSS classes: `field`, `field__label`, `field__items`, and `field__item`, and introduce one new one: `field__item__label`. If **View title** is any value but *Hidden*, each view display title will be rendered with the `field__item__label` class.

Viewfield does not provide any CSS styles, since Drupal core does not provide default styling for fields. In Drupal, styling for fields is provided by the site theme. To help style `field__item__label` components, we can look at the way the site theme treats `field__label` components, and add our own similar styling for `field_item_label`.

For example, the Classy theme field styles are found in `core/themes/classy/css/components/field.css`. Looking in that file for how `field__label` is styled, we can derive the the following CSS rule to make `field__item__label` to appear visually identitical:

```
.field__item__label {
  font-weight: bold;
}
```
