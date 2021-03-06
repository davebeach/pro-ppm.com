<?php

namespace Drupal\schema_item_list\Plugin\metatag\Tag;

use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaItemListBase;

/**
 * Provides a plugin for the 'schema_item_list_element' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_item_list_element",
 *   label = @Translation("itemListElement"),
 *   description = @Translation(""),
 *   name = "itemListElement",
 *   group = "schema_item_list",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaItemListElement extends SchemaItemListBase {

}
