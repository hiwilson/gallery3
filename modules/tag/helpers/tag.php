<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2009 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class tag_Core {
  /**
   * Associate a tag with an item.  Create the tag if it doesn't already exist.
   *
   * @todo Write test.
   *
   * @param Item_Model $item an item
   * @param string     $tag_name a tag name
   * @return Tag_Model
   * @throws Exception("@todo {$tag_name} WAS_NOT_ADDED_TO {$item->id}")
   */
  static function add($item, $tag_name) {
    if (empty($tag_name)) {
      throw new exception("@todo MISSING_TAG_NAME");
    }

    $tag = ORM::factory("tag")->where("name", $tag_name)->find();
    if (!$tag->loaded) {
      $tag->name = $tag_name;
      $tag->count = 0;
      $tag->save();
    }

    if (!$tag->has($item)) {
      if (!$tag->add($item, $tag)) {
        throw new Exception("@todo {$tag->name} WAS_NOT_ADDED_TO {$item->id}");
      }
      $tag->count++;
      $tag->save();
    }
    return $tag;
  }

  /**
   * Modify the tags associate with an item.
   *
   * @param Item_Model $item an item
   * @param string     $new_tags_string a string of new tags name seperated by ;
   * @return null
   * @throws Exception("@todo {$tag_name} WAS_NOT_ADDED_TO {$item->id}")
   * @throws Exception("@todo {$tag_name} WAS_NOT_DELETED_TO {$item->id}")
   */
  static function update($item, $new_tags_string) {
    $old_tags = self::get_tags($item);

    $new_tags = preg_split("/[,;]/", $new_tags_string);
    foreach ($new_tags as $i => $new_tag) {
      $new_tags[$i] = trim($new_tag);
    }

    $add_tags = array_diff($new_tags, $old_tags);
    foreach ($add_tags as $tag_name) {
      if (empty($tag_name)) continue;
      $tag = ORM::factory("tag")->where("name", $tag_name)->find();
      if (!$tag->loaded) {
        $tag->name = $tag_name;
        $tag->count = 0;
        $tag->save();
      }
      if (!$tag->has($item)) {
        if (!$tag->add($item, $tag)) {
          throw new Exception("@todo {$tag->name} WAS_NOT_ADDED_TO {$item->id}");
        }
        $tag->count++;
        $tag->save();
      }
    }
    
    $del_tags = array_diff($old_tags, $new_tags);
    foreach ($del_tags as $tag_name) {
      $tag = ORM::factory("tag")->where("name", $tag_name)->find();
      if ($tag->has($item)) {
        if (!$tag->remove($item, $tag)) {
          throw new Exception("@todo {$tag->name} WAS_NOT_DELETED_TO {$item->id}");
        }
        $tag->save();
        $tag->count--;
        if ($tag->count <= 0) {
          $tag->delete();
        } else {
          $tag->save();
        }
      }
    }
  }

  /**
   * Return the N most popular tags.
   *
   * @return ORM_Iterator of Tag_Model in descending tag count order
   */
  static function popular_tags($count) {
    return ORM::factory("tag")
      ->orderby("count", "DESC")
      ->limit($count)
      ->find_all();
  }

  /**
   * Return a rendering of the cloud for the N most popular tags.
   *
   * @param integer $count the number of tags
   * @return View
   */
  static function cloud($count) {
    $tags = tag::popular_tags($count)->as_array();
    if ($tags) {
      $cloud = new View("tag_cloud.html");
      $cloud->max_count = $tags[0]->count;
      usort($tags, array("tag_theme", "sort_by_name"));
      $cloud->tags = $tags;
      return $cloud;
    }
  }

  static function get_add_form($item) {
    $form = new Forge("tags", "", "post", array("id" => "gAddTagForm"));
    $label = $item->is_album() ?
      t("Add tag to album") :
      ($item->is_photo() ? t("Add tag to photo") : t("Add tag to movie"));

    $group = $form->group("add_tag")->label("Add Tag");
    $group->input("name")->label($label)->rules("required|length[1,64]");
    $group->hidden("item_id")->value($item->id);
    $group->submit("")->value(t("Add Tag"));
    return $form;
  }

  static function get_rename_form($tag) {
    $form = new Forge("admin/tags/rename/$tag->id", "", "post", array("id" => "gRenameTagForm"));
    $group = $form->group("rename_tag")->label(t("Rename Tag"));
    $group->input("name")->label(t("Tag name"))->value($tag->name)->rules("required|length[1,64]");
    $group->inputs["name"]->error_messages("in_use", t("There is already a tag with that name"));
    $group->submit("")->value(t("Save"));
    return $form;
  }

  static function get_delete_form($tag) {
    $form = new Forge("admin/tags/delete/$tag->id", "", "post", array("id" => "gDeleteTagForm"));
    $group = $form->group("delete_tag")
      ->label(t("Really delete tag %tag_name?", array("tag_name" => $tag->name)));
    $group->submit("")->value(t("Delete Tag"));
    return $form;
  }
  
  static function get_tags($item) {
    $records = ORM::factory("item")
                    ->select("tags.name as tag_name")
                    ->join("items_tags", "items.id", "items_tags.item_id", "left")
                    ->join("tags", "items_tags.tag_id", "tags.id", "left")
                    ->where("items.id", $item->id)
                    ->find_all();
    $tags = array(); 
    foreach ($records as $record) {
      $tags[] = $record->tag_name;
    }
    return $tags; 
  }
}