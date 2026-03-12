<?php
require_once 'conn.php';

//     
$i18nTexts = [
    'ko' => [
        'inquiry' => '',
        'inquiryHistory' => ' ',
        'callSupport' => ' ',
        'weekdaysHours' => ' 9 - 18',
        'totalInquiries' => ' 4',
        'productInquiry' => ' ',
        'pendingReply' => ' ',
        'replied' => ' ',
        'inquiryTitle' => ' ',
        'loadInquiriesFailed' => '  .',
        'loadingInquiries' => '  ...',
        'newInquiry' => ' ',
        'noInquiries' => ' .',
        'noPendingInquiries' => '  .',
        'noInProgressInquiries' => '  .',
        'noResolvedInquiries' => '  .',
        'noClosedInquiries' => '  .'
    ],
    'en' => [
        'inquiry' => 'Inquiry',
        'inquiryHistory' => 'Inquiry History',
        'callSupport' => 'Call Support',
        'weekdaysHours' => 'Weekdays 9 AM - 6 PM',
        'totalInquiries' => 'Total 4',
        'productInquiry' => 'Product Inquiry',
        'pendingReply' => 'Pending Reply',
        'replied' => 'Replied',
        'inquiryTitle' => 'Inquiry Title',
        'loadInquiriesFailed' => 'Failed to load inquiries.',
        'loadingInquiries' => 'Loading inquiries...',
        'newInquiry' => 'New Inquiry',
        'noInquiries' => 'No inquiries.',
        'noPendingInquiries' => 'No pending inquiries.',
        'noInProgressInquiries' => 'No inquiries in progress.',
        'noResolvedInquiries' => 'No resolved inquiries.',
        'noClosedInquiries' => 'No closed inquiries.'
    ],
    'tl' => [
        'inquiry' => 'Magtanong',
        'inquiryHistory' => 'Kasaysayan ng mga Tanong',
        'callSupport' => 'Tawag sa Suporta',
        'weekdaysHours' => 'Mga Araw ng Linggo 9 AM - 6 PM',
        'totalInquiries' => 'Kabuuang 4',
        'productInquiry' => 'Tanong sa Produkto',
        'pendingReply' => 'Naghihintay ng Sagot',
        'replied' => 'Nasagot na',
        'inquiryTitle' => 'Pamagat ng Tanong',
        'loadInquiriesFailed' => 'Nabigo sa pag-load ng mga tanong.',
        'loadingInquiries' => 'Naglo-load ng mga tanong...',
        'newInquiry' => 'Bagong Tanong',
        'noInquiries' => 'Walang mga tanong.',
        'noPendingInquiries' => 'Walang mga tanong na naghihintay.',
        'noInProgressInquiries' => 'Walang mga tanong na ginagawa.',
        'noResolvedInquiries' => 'Walang mga tanong na nasagot.',
        'noClosedInquiries' => 'Walang mga tanong na sarado.'
    ]
];

try {
    foreach ($i18nTexts as $langCode => $texts) {
        foreach ($texts as $textKey => $textValue) {
            $stmt = $conn->prepare("INSERT INTO i18n_texts (languageCode, textKey, textValue, category) VALUES (?, ?, ?, 'inquiry') ON DUPLICATE KEY UPDATE textValue = VALUES(textValue)");
            $stmt->bind_param("sss", $langCode, $textKey, $textValue);
            $stmt->execute();
        }
    }
    
    echo "     .\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
}

$conn->close();
?>
