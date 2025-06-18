<?php

namespace Utilities;

function key_values_intersect($values, $keys) {
  foreach($keys as $key) {
    if (isset($values[$key])) {
      $key_val_int[$key] = $values[$key];
    }
  }
  return $key_val_int;
}

function encodeURIComponent($str) {
  return str_replace(
    array('%', ':', '/', '\\'),
    array('%25', '%3A', '%2F', '%5C'),
    $str
  );
}
