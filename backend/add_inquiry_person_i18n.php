<?php
require_once 'conn.php';

//      
$i18nTexts = [
    'ko' => [
        'oneOnOneInquiry' => '1:1 ',
        'replyEmailAddress' => '   ',
        'replyPhoneNumber' => '   ',
        'inquiryType' => ' ',
        'selectInquiryType' => '  ',
        'option1' => 'Option 1',
        'option2' => 'Option 2',
        'option3' => 'Option 3',
        'inquiryContent' => ' ',
        'enterTitle' => ' ',
        'enterContent' => ' ',
        'fileAttachment' => '',
        'upload' => '',
        'fileUploadLimit' => '*     5  ',
        'fileFormatLimit' => '* JPG, JPEG, PNG, GIF, PDF    ,   10MB  .',
        'register' => ''
    ],
    'en' => [
        'oneOnOneInquiry' => '1:1 Inquiry',
        'replyEmailAddress' => 'Reply Email Address',
        'replyPhoneNumber' => 'Reply Phone Number',
        'inquiryType' => 'Inquiry Type',
        'selectInquiryType' => 'Please select inquiry type',
        'option1' => 'Option 1',
        'option2' => 'Option 2',
        'option3' => 'Option 3',
        'inquiryContent' => 'Inquiry Content',
        'enterTitle' => 'Please enter title',
        'enterContent' => 'Please enter content',
        'fileAttachment' => 'File Attachment',
        'upload' => 'Upload',
        'fileUploadLimit' => '* Up to 5 photos and files can be uploaded',
        'fileFormatLimit' => '* Only JPG, JPEG, PNG, GIF, PDF format files are allowed, and each file must be less than 10MB.',
        'register' => 'Register'
    ],
    'tl' => [
        'oneOnOneInquiry' => '1:1 Inquiry',
        'replyEmailAddress' => 'Email Address para sa Sagot',
        'replyPhoneNumber' => 'Numero ng Telepono para sa Sagot',
        'inquiryType' => 'Uri ng Tanong',
        'selectInquiryType' => 'Piliin ang uri ng tanong',
        'option1' => 'Option 1',
        'option2' => 'Option 2',
        'option3' => 'Option 3',
        'inquiryContent' => 'Nilalaman ng Tanong',
        'enterTitle' => 'Ilagay ang pamagat',
        'enterContent' => 'Ilagay ang nilalaman',
        'fileAttachment' => 'Kalakip na File',
        'upload' => 'I-upload',
        'fileUploadLimit' => '* Hanggang 5 na larawan at file ang maaaring i-upload',
        'fileFormatLimit' => '* JPG, JPEG, PNG, GIF, PDF format na file lamang ang pinapayagan, at bawat file ay dapat na mas mababa sa 10MB.',
        'register' => 'Magparehistro'
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
    
    echo "      .\n";
    
} catch (Exception $e) {
    echo " : " . $e->getMessage() . "\n";
}

$conn->close();
?>
