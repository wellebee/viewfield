# Drupal Viewfield 8.x-3.x

## Features

The viewfield module defines an entity reference field type to display a view.

## Installation

Install the module using the Drupal UI, `drush`, or `composer`.

## Field Configuration

To add a Viewfield to an fieldable entity type (e.g., a content type): 
1. Edit the content type and navigate to the **Manage Fields** tab. 
2. Choose **+ Add Field** and select **Viewfield** under the **Reference** category.
3. Choose the number of field values (distinct view displays) you want to use and press **Save Field Settings**.
4. Next come the field settings on the field edit tab. 
5. Selecting **Required field** constrains the field to always contain a value.
6. Assign one or more default values to be used for newly created content, or for use with the **Always use default value** option.
7. Selecting **Always use default value** means the Viewfield will always use the provided default value(s) when rendering the field, and this field will be hidden in all content editing forms, making it unnecessary to assign values individually to each piece of content. Nice! If this is checked, you **must** provide a default value.
8. The set of allowed views and allowed display types may be restricted on content edit forms using the **Allowed views** and **Allowed display types** checkboxes respectively.
9. Press **Save settings** to complete the field creation process.

## Assigning Field Values
Assigning a value (or default value) to a Viewfield consists of selecting a View and Display from the select boxes, and providing an optional comma-delimited list of arguments (contextual filters) for the display. The argument list may contain tokens. Token help is available by clicking on the **Browse available tokens** link shown below the arguments field.

## Output and Theming
Viewfield has extensive theming support. The default formatter supports the following settings:

- **Always build output**
Produce rendered output even if the view produces no results.
This option may be useful for some specialized cases, e.g., to force rendering of an attachment display even if there are no view results.
- **Hide field label**
Hide the label (name) of the field when the field is rendered.
This option may be useful when including view display titles.
- **Include view title**
Include the view display title in the output.
- **Show empty view title**
Show the view title even when the view produces no results.
This option has an effect only when Always build output is also selected.

Each Viewfield item (value) is themed with the `viewfield-item.html.twig` template, which may be overridden.
