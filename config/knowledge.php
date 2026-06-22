<?php

return [

    'max_items' => 500,
    'max_title_length' => 200,
    'max_body_length' => 20000,
    'max_search_query_length' => 120,
    'max_search_results' => 20,
    'max_document_size_kb' => 10240,
    'allowed_document_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ],
    'supported_currencies' => ['INR', 'USD', 'GBP', 'EUR'],
    'max_import_rows' => 500,

];
