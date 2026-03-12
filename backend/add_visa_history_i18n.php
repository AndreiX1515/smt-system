<?php
require_once 'conn.php';

//      
$i18nTexts = [
    'ko' => [
        'visaApplicationHistory' => '  ',
        'inadequateDocuments' => ' ',
        'duringExamination' => '',
        'completionIssuance' => ' ',
        'rebellion' => '',
        'loadVisaHistoryFailed' => '    .',
        'loadingVisaHistory' => '    ...',
        'newVisaApplication' => '  ',
        'noVisaHistory' => '   .',
        'noInadequateVisas' => '   .',
        'noUnderReviewVisas' => '  .',
        'noApprovedVisas' => '   .',
        'noRejectedVisas' => '  .'
    ],
    'en' => [
        'visaApplicationHistory' => 'Visa Application History',
        'inadequateDocuments' => 'Inadequate Documents',
        'duringExamination' => 'Under Review',
        'completionIssuance' => 'Issued',
        'rebellion' => 'Rejected',
        'loadVisaHistoryFailed' => 'Failed to load visa application history.',
        'loadingVisaHistory' => 'Loading visa application history...',
        'newVisaApplication' => 'New Visa Application',
        'noVisaHistory' => 'No visa application history.',
        'noInadequateVisas' => 'No visas with inadequate documents.',
        'noUnderReviewVisas' => 'No visas under review.',
        'noApprovedVisas' => 'No approved visas.',
        'noRejectedVisas' => 'No rejected visas.'
    ],
    'tl' => [
        'visaApplicationHistory' => 'Kasaysayan ng Visa Application',
        'inadequateDocuments' => 'Kulang na Dokumento',
        'duringExamination' => 'Sa Pagsusuri',
        'completionIssuance' => 'Na-issue',
        'rebellion' => 'Tinanggihan',
        'loadVisaHistoryFailed' => 'Nabigo sa pag-load ng kasaysayan ng visa application.',
        'loadingVisaHistory' => 'Naglo-load ng kasaysayan ng visa application...',
        'newVisaApplication' => 'Bagong Visa Application',
        'noVisaHistory' => 'Walang kasaysayan ng visa application.',
        'noInadequateVisas' => 'Walang mga visa na kulang ang dokumento.',
        'noUnderReviewVisas' => 'Walang mga visa na nasa pagsusuri.',
        'noApprovedVisas' => 'Walang mga visa na naaprubahan.',
        'noRejectedVisas' => 'Walang mga visa na tinanggihan.'
    ]
];

try {
    foreach ($i18nTexts as $langCode => $texts) {
        foreach ($texts as $textKey => $textValue) {
            $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, 'visa') ON DUPLICATE KEY UPDATE textValue = VALUES(textValue)");
            $stmt->bind_param("sss", $langCode, $textKey, $textValue);
            $stmt->execute();
        }
    }
    
    echo "      .\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
}

$conn->close();
?>
