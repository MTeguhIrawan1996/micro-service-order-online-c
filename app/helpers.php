<?php

use Illuminate\Support\Facades\Http;

function createPremiumAccess($data)
{
  $url = env('SERVICE_COURSE_URL') . 'api/my-courses/premium';

  try {
    $response = Http::post($url, $data);

    $data = $response->json();
    return $data;
  } catch (\Throwable $e) {
    return [
      "success" => false,
      "status" => 500,
      "message" => "Internal Server Error"
    ];
  }
}