<?php
/**
 * doctors.php — Specialty mapping and provider data
 *
 * HOW IT WORKS:
 * Mayo Clinic article responses include a meta.controlWords.category tree.
 * Each node in that tree has an mcvId — a Mayo Controlled Vocabulary ID.
 * $specialtyMap maps those IDs to a specialty name.
 * $doctorsBySpecialty maps specialty names to placeholder provider data.
 *
 * TO ADD A NEW SPECIALTY:
 * 1. Find the mcvId in the article's JSON response (meta.controlWords.category)
 * 2. Add it to $specialtyMap with the specialty name
 * 3. Add provider entries to $doctorsBySpecialty under that specialty name
 *
 * mcvId reference — common values seen in responses:
 *   1092  Diagnosable condition (generic)
 *   1104  Pregnancy
 *   1137  Cardiovascular / Digestive system (context-dependent)
 *   1255  Quit smoking
 *   1452  Intestine
 *   1770  Pain NOS
 *   1898  Pregnancy week by week
 *   1006  Lifestyle
 *   1090  Fitness
 *   1094  Nutrition and healthy eating
 *   1100  Aerobic exercise
 *   2082  Cancer
 *   2375  Breast cancer
 *   2926  Gas and gas pains
 *   127048 Physical activity
 *   138500 Gastrointestinal tract
 */

// Maps Mayo mcvId -> specialty name
$specialtyMap = [
    '1092'   => 'Internal Medicine', // Diagnosable condition (generic)
    '1104'   => 'OB/GYN',            // Pregnancy
    '1137'   => 'Cardiology',        // Cardiovascular system
    '1255'   => 'Pulmonology',       // Quit smoking / Tobacco use
    '1452'   => 'Gastroenterology',  // Intestine
    '1770'   => 'Pain Management',   // Pain NOS
    '1898'   => 'OB/GYN',            // Pregnancy week by week
    '1006'   => 'Wellness',          // Lifestyle
    '1090'   => 'Sports Medicine',   // Fitness
    '1094'   => 'Nutrition',         // Nutrition and healthy eating
    '1100'   => 'Sports Medicine',   // Aerobic exercise
    '2082'   => 'Oncology',          // Cancer
    '2375'   => 'Oncology',          // Breast cancer
    '2926'   => 'Gastroenterology',  // Gas and gas pains
    '127048' => 'Sports Medicine',   // Physical activity
    '138500' => 'Gastroenterology',  // Gastrointestinal tract
];

// Maps specialty name -> array of provider placeholder data
// Replace name, title, and phone with real provider data when available.
$doctorsBySpecialty = [
    'Cardiology' => [
        ['name' => 'Dr. Sarah Mitchell', 'title' => 'Cardiologist',        'phone' => '(555) 201-1000'],
        ['name' => 'Dr. James Harlow',   'title' => 'Cardiologist',        'phone' => '(555) 201-1001'],
    ],
    'Gastroenterology' => [
        ['name' => 'Dr. Linda Park',     'title' => 'Gastroenterologist',  'phone' => '(555) 201-2000'],
        ['name' => 'Dr. Tom Reyes',      'title' => 'Gastroenterologist',  'phone' => '(555) 201-2001'],
    ],
    'Oncology' => [
        ['name' => 'Dr. Rachel Nguyen',  'title' => 'Oncologist',          'phone' => '(555) 201-3000'],
        ['name' => 'Dr. Brian Foster',   'title' => 'Oncologist',          'phone' => '(555) 201-3001'],
    ],
    'OB/GYN' => [
        ['name' => "Dr. Maria O'Brien",  'title' => 'OB/GYN',              'phone' => '(555) 201-4000'],
        ['name' => 'Dr. Susan Caldwell', 'title' => 'OB/GYN',              'phone' => '(555) 201-4001'],
    ],
    'Pain Management' => [
        ['name' => 'Dr. Kevin Walsh',    'title' => 'Pain Specialist',     'phone' => '(555) 201-5000'],
    ],
    'Pulmonology' => [
        ['name' => 'Dr. Alan Torres',    'title' => 'Pulmonologist',       'phone' => '(555) 201-6000'],
    ],
    'Sports Medicine' => [
        ['name' => 'Dr. Chris Daniels',  'title' => 'Sports Medicine',     'phone' => '(555) 201-7000'],
    ],
    'Nutrition' => [
        ['name' => 'Dr. Amy Chen',       'title' => 'Dietitian',           'phone' => '(555) 201-8000'],
    ],
    'Internal Medicine' => [
        ['name' => 'Dr. Robert Ellis',   'title' => 'Internal Medicine',   'phone' => '(555) 201-9000'],
        ['name' => 'Dr. Paula Greene',   'title' => 'Internal Medicine',   'phone' => '(555) 201-9001'],
    ],
    'Wellness' => [
        ['name' => 'Dr. Diane Brooks',   'title' => 'Wellness Physician',  'phone' => '(555) 201-0000'],
    ],
];
