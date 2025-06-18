<?php

// Since we use our own custom Cloudfront distribution to convert images to
// webp, we need to delete the generated webp images and invalidate the cache
// when the image is replaced.
add_action('enable-media-replace-upload-done', function ($target_url, $source_url, $post_id) {
  if ($target_url === $source_url) {
    invalidateCloudfront($target_url);
  }
}, 10, 3);

function generateRandomString($length = 10) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
};

function awsCreds() {
  global $as3cf;
  return [
    'version'     => 'latest',
    'region'      => 'us-east-1',
    'credentials' => [
      'key'    => $as3cf->get_setting('access-key-id'),
      'secret' => $as3cf->get_setting('secret-access-key')
    ]
  ];
}

function allImages($path) {
  global $as3cf;
  $s3     = new Aws\S3\S3Client(awsCreds());
  $bucket = $as3cf->get_setting('bucket');
  $result = $s3->listObjectsV2([
    'Bucket' => $bucket,
    'Prefix' => $path
  ]);
  $contents = $result['Contents'] ?? [];
  $images   = array_column($contents, 'Key');
  return $images;
}

function s3DeleteWebpObjects($images) {
  global $as3cf;
  $s3   = new Aws\S3\S3Client(awsCreds());
  $keys = array_filter($images, function($image) {
    return preg_match('/\.webp$/', $image);
  });
  $keys = array_map(function($key) {
    return ['Key' => $key];
  }, $keys);

  $result = Aws\S3\BatchDelete::fromIterator(
    $s3,
    $as3cf->get_setting('bucket'),
    new \ArrayIterator($keys)
  );
  $result->delete();
}

function cloudfrontDistro() {
  // FIXME: this should be pulled from a config file or environment variable
  return [
    'beta'        => 'XXXXXXXXXXXXXX',
    'production'  => 'XXXXXXXXXXXXXX',
    'development' => 'XXXXXXXXXXXXXX',
  ][WP_ENV];
}

function invalidateCloudfront($url) {
  global $as3cf;
  $path   = parse_url($url, PHP_URL_PATH);
  $ext    = pathinfo($path, PATHINFO_EXTENSION);
  $path   = str_replace(".$ext", '', $path);
  $caller = generateRandomString(16);
  $distro = cloudfrontDistro();
  $path   = ltrim($path, '/');
  $images = allImages($path);
  $paths  = array_map(function($image) {
    return "/$image";
  }, $images);

  s3DeleteWebpObjects($images);

  $cloudFront = new Aws\CloudFront\CloudFrontClient(awsCreds());

  $settings = [
    'DistributionId' => $distro,
    'InvalidationBatch' => [
        'CallerReference' => $caller,
        'Paths' => [
            'Items' => $paths,
            'Quantity' => count($paths)
        ]
    ]
  ];

  $result = $cloudFront->createInvalidation($settings);
}
