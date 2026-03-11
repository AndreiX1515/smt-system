<?php
/**
 * WeatherAPI.com Configuration
 */

define('WEATHER_API_KEY', '2a66d92995b74683a00141811261103');
define('WEATHER_API_BASE', 'https://api.weatherapi.com/v1');

/**
 * Get weather forecast for a destination and date range
 * Returns array of daily forecasts or placeholder if too far in advance
 *
 * @param string $destination City/country name
 * @param string $startDate   Y-m-d
 * @param int    $days        Number of days
 * @return array
 */
function getWeatherForecast($destination, $startDate, $days = 6) {
    $now = new DateTime();
    $start = new DateTime($startDate);
    $diffDays = (int)$now->diff($start)->format('%r%a');

    // WeatherAPI free plan supports up to 14 days forecast
    if ($diffDays > 14) {
        return [
            'available' => false,
            'message' => 'Weather forecast will be available closer to departure date',
            'forecasts' => []
        ];
    }

    $forecasts = [];
    $url = WEATHER_API_BASE . '/forecast.json?' . http_build_query([
        'key' => WEATHER_API_KEY,
        'q' => $destination,
        'days' => min($days, 14),
        'aqi' => 'no',
        'alerts' => 'no'
    ]);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return [
            'available' => false,
            'message' => 'Weather data temporarily unavailable',
            'forecasts' => []
        ];
    }

    $data = json_decode($response, true);
    if (!$data || isset($data['error'])) {
        return [
            'available' => false,
            'message' => 'Weather data unavailable for this destination',
            'forecasts' => []
        ];
    }

    if (isset($data['forecast']['forecastday'])) {
        foreach ($data['forecast']['forecastday'] as $day) {
            $forecasts[] = [
                'date' => $day['date'],
                'condition' => $day['day']['condition']['text'] ?? '',
                'icon' => $day['day']['condition']['icon'] ?? '',
                'maxtemp_c' => $day['day']['maxtemp_c'] ?? '',
                'mintemp_c' => $day['day']['mintemp_c'] ?? '',
                'avgtemp_c' => $day['day']['avgtemp_c'] ?? '',
                'humidity' => $day['day']['avghumidity'] ?? '',
                'chance_of_rain' => $day['day']['daily_chance_of_rain'] ?? ''
            ];
        }
    }

    return [
        'available' => true,
        'message' => '',
        'forecasts' => $forecasts
    ];
}
