<?php

function kp_nuxt_contact_form_data($value, $post_id, $field) {
  $wp_home = get_home_url(get_current_blog_id());
  $results = ['action' => "$wp_home/wp-json/contact-form-7/v1/contact-forms/$value/feedback"];
  $contact_form = WPCF7_ContactForm::get_instance($value);
  if (!$contact_form) return $value;

  $results['fields'] = array_map(function ($form_field) {
    $array = json_decode(json_encode($form_field), true);
    $array['required'] = str_contains($array['type'], '*');
    return $array;
  }, $contact_form->scan_form_tags());

  $dom = new DOMDocument();
  $dom->loadHTML($contact_form->form_html());
  $elements = iterator_to_array((new DOMXPath($dom))->query('//input[@type="hidden"]'));
  $results['hidden_fields'] = array_reduce($elements, function ($acc, $element) {
    $acc[$element->getAttribute('name')] = $element->getAttribute('value');
    return $acc;
  }, []);

  return $results;
}

add_filter('acf/format_value/name=contact_form', 'kp_nuxt_contact_form_data', 10, 3);
