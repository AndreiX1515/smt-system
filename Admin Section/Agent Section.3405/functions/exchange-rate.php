<?php
  $apiKey = '77dc42e0276c97b3f723a125';
  $timeInterval = 3600; // 1 hour in seconds
  $lastFetchFile = '../Agent Section/miscellaneous/last_fetch_time.txt'; // File to store last fetch time
  $exchangeRatesFile = '../Agent Section/miscellaneous/exchange_rates.json'; // File to store exchange rates

  // Function to fetch the latest conversion rates
  function getExchangeRates($apiKey) {
      $url = "https://v6.exchangerate-api.com/v6/$apiKey/latest/USD"; // USD as the base currency
      $response = file_get_contents($url);
      return json_decode($response, true);
  }

  // Check last fetch time
  if (file_exists($lastFetchFile)) {
      $lastFetchTime = (int)file_get_contents($lastFetchFile);

      // Fetch new rates if last fetch was more than 1 hour ago
      if (time() - $lastFetchTime >= $timeInterval) {
          $exchangeRates = getExchangeRates($apiKey);
          file_put_contents($exchangeRatesFile, json_encode($exchangeRates));
          file_put_contents($lastFetchFile, time()); // Update last fetch time
      } else {
          // Load rates from saved file
          $exchangeRates = json_decode(file_get_contents($exchangeRatesFile), true);
      }
  } else {
      // First run, fetch rates from API
      $exchangeRates = getExchangeRates($apiKey);
      file_put_contents($exchangeRatesFile, json_encode($exchangeRates));
      file_put_contents($lastFetchFile, time()); // Set initial fetch time
  }

  // Extract conversion rates or use fallback values
  $usd_to_php = $exchangeRates['conversion_rates']['PHP'] ?? 56.50; // Fallback if not found
  $usd_to_krw = $exchangeRates['conversion_rates']['KRW'] ?? 1320;  // Fallback if not found
  $usd_to_euro = $exchangeRates['conversion_rates']['EUR'] ?? 0.85;  // Fallback if not found
  ?>