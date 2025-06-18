<?php

if (!function_exists('str_starts_with')) {
  function str_starts_with($str, $start) {
    return (@substr_compare($str, $start, 0, strlen($start))==0);
  }
}

class RedirectCsv {
  private $csv;
  private $prefix;
  private $home;
  private $filename;

  public function __construct($csv) {
    $home = getenv('FRONTEND_URL');
    $path = rtrim(get_blog_details()->path, '/');
    $this->filename = $csv;
    $this->prefix   = (empty($path)) ? '/' : $path;
    $this->csv      = $this->csv_to_array($csv);
    $this->home     = $home;
  }

  public function update_prefix() {
    $new_csv = array();
    foreach ($this->csv as $idx => $row) {
      if (0 === $idx) array_push($new_csv, array_keys($row));
      $row['Origin'] = $this->update_origin_prefix($row['Origin']);
      $row['Target'] = $this->update_target_prefix($row['Target']);
      $url = $row['Target'];
      $headers = @get_headers($url);
      if ($headers && strpos( $headers[0], '200')) {
        array_push($new_csv, array_values($row));
      }
    }
    $this->array_to_csv($new_csv);
    return $new_csv;
  }

  private function update_target_prefix($target) {
    if (str_starts_with($target, 'http')) return $target;
    $target = $this->update_origin_prefix($target);
    return $this->home . $target;
  }

  private function update_origin_prefix($origin) {
    if (str_starts_with($origin, $this->prefix)) return $origin;
    return $this->prefix . $origin;
  }

  private function csv_to_array($csv) {
    $csv  = array_map("str_getcsv", file($csv, FILE_SKIP_EMPTY_LINES));
    $keys = array_shift($csv);
    foreach ($csv as $i => $row) $csv[$i] = array_combine($keys, $row);
    return $csv;
  }

  private function array_to_csv($array) {
    $filename = explode('.csv', $this->filename)[0] . '-updated.csv';
    $file     = fopen($filename, 'w');
    foreach ($array as $fields) fputcsv($file, $fields);
    fclose($file);
  }
}
