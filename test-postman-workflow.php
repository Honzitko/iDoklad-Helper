<?php
/**
 * Test script that exactly matches the working Postman collection workflow
 */

// Include WordPress functions (adjust path as needed)
if (!defined('ABSPATH')) {
    // For testing outside WordPress, define basic functions
    if (!function_exists('wp_remote_request')) {
        function wp_remote_request($url, $args = array()) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            if (isset($args['method'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $args['method']);
            }
            
            if (isset($args['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
            }
            
            if (isset($args['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return array(
                'body' => $response,
                'response' => array('code' => $http_code)
            );
        }
        
        function wp_remote_retrieve_response_code($response) {
            return $response['response']['code'];
        }
        
        function wp_remote_retrieve_body($response) {
            return $response['body'];
        }
        
        function is_wp_error($response) {
            return false;
        }
    }
}

// Test configuration - Update these with your actual credentials
$client_id = 'YOUR_CLIENT_ID'; // Replace with your actual client ID
$client_secret = 'YOUR_CLIENT_SECRET'; // Replace with your actual client secret

// Environment variables (matching Postman collection)
$base_url = 'https://api.idoklad.cz/v3';
$token_url = 'https://identity.idoklad.cz/server/connect/token';

echo "=== iDoklad API v3 Postman Collection Workflow Test ===\n\n";

// Step 1: Get OAuth2 token (Client Credentials)
echo "Step 1: Getting OAuth2 token (Client Credentials)...\n";

$token_request_body = array(
    'grant_type' => 'client_credentials',
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'scope' => 'idoklad_api'
);

$token_args = array(
    'method' => 'POST',
    'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded'
    ),
    'body' => http_build_query($token_request_body),
    'timeout' => 30
);

$token_response = wp_remote_request($token_url, $token_args);

if (is_wp_error($token_response)) {
    die("Token request failed: " . $token_response->get_error_message() . "\n");
}

$token_response_code = wp_remote_retrieve_response_code($token_response);
$token_response_body = wp_remote_retrieve_body($token_response);
$token_data = json_decode($token_response_body, true);

if ($token_response_code !== 200) {
    die("Token request failed with status $token_response_code: $token_response_body\n");
}

$access_token = $token_data['access_token'];
echo "✓ Token received successfully\n\n";

// Step 2: (Optional) Create Partner
echo "Step 2: Creating partner...\n";

$partner_data = array(
    'CompanyName' => 'AUTO TEST COMPANY s.r.o.',
    'Email' => 'autotest+partner@example.com',
    'CountryId' => 1,
    'Street' => 'Test 1',
    'City' => 'Praha',
    'PostalCode' => '11000'
);

$partner_args = array(
    'method' => 'POST',
    'headers' => array(
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($partner_data),
    'timeout' => 30
);

$partner_response = wp_remote_request($base_url . '/Contacts', $partner_args);

if (is_wp_error($partner_response)) {
    die("Partner creation failed: " . $partner_response->get_error_message() . "\n");
}

$partner_response_code = wp_remote_retrieve_response_code($partner_response);
$partner_response_body = wp_remote_retrieve_body($partner_response);
$partner_response_data = json_decode($partner_response_body, true);

if (!in_array($partner_response_code, array(200, 201))) {
    die("Partner creation failed with status $partner_response_code: $partner_response_body\n");
}

$partner_id = null;
$partner_inner = null;
if (isset($partner_response_data['Data'])) {
    $partner_inner = $partner_response_data['Data'];
} elseif (isset($partner_response_data['data'])) {
    $partner_inner = $partner_response_data['data'];
} else {
    $partner_inner = $partner_response_data;
}

if (is_array($partner_inner)) {
    if (isset($partner_inner['Id'])) {
        $partner_id = $partner_inner['Id'];
    } elseif (isset($partner_inner['id'])) {
        $partner_id = $partner_inner['id'];
    } elseif (isset($partner_inner['Data']['Id'])) {
        $partner_id = $partner_inner['Data']['Id'];
    } elseif (isset($partner_inner[0]['Id'])) {
        $partner_id = $partner_inner[0]['Id'];
    } elseif (isset($partner_inner[0]['id'])) {
        $partner_id = $partner_inner[0]['id'];
    }
}

if (!$partner_id && isset($partner_response_data['Id'])) {
    $partner_id = $partner_response_data['Id'];
} elseif (!$partner_id && isset($partner_response_data['id'])) {
    $partner_id = $partner_response_data['id'];
}

echo "✓ Partner created with ID: $partner_id\n\n";

// Step 3: Resolve NumericSequence (IssuedInvoices)
echo "Step 3: Resolving NumericSequence (IssuedInvoices)...\n";

$numeric_args = array(
    'method' => 'GET',
    'headers' => array(
        'Authorization' => 'Bearer ' . $access_token
    ),
    'timeout' => 30
);

$numeric_response = wp_remote_request($base_url . '/NumericSequences', $numeric_args);

if (is_wp_error($numeric_response)) {
    die("NumericSequences request failed: " . $numeric_response->get_error_message() . "\n");
}

$numeric_response_code = wp_remote_retrieve_response_code($numeric_response);
$numeric_response_body = wp_remote_retrieve_body($numeric_response);
$numeric_data = json_decode($numeric_response_body, true);

if ($numeric_response_code !== 200) {
    die("NumericSequences request failed with status $numeric_response_code: $numeric_response_body\n");
}

// Handle different response structures (following Postman logic)
$list = array();
if (isset($numeric_data['Data']) && isset($numeric_data['Data']['Items'])) {
    $list = $numeric_data['Data']['Items'];
} elseif (isset($numeric_data['data']) && isset($numeric_data['data']['items'])) {
    $list = $numeric_data['data']['items'];
} elseif (isset($numeric_data['Items'])) {
    $list = $numeric_data['Items'];
} elseif (isset($numeric_data['items'])) {
    $list = $numeric_data['items'];
} elseif (isset($numeric_data['Data'])) {
    $list = is_array($numeric_data['Data']) ? $numeric_data['Data'] : array($numeric_data['Data']);
} elseif (isset($numeric_data['data'])) {
    $list = is_array($numeric_data['data']) ? $numeric_data['data'] : array($numeric_data['data']);
} else {
    $list = is_array($numeric_data) ? $numeric_data : array($numeric_data);
}

if (empty($list) || !is_array($list)) {
    die("No numeric sequences returned.\n");
}

// Find sequence: first try DocumentType = 0 AND IsDefault, then DocumentType = 0, then first available
$numeric_sequence = null;
foreach ($list as $sequence) {
    if (isset($sequence['DocumentType']) && $sequence['DocumentType'] === 0 && isset($sequence['IsDefault']) && $sequence['IsDefault']) {
        $numeric_sequence = $sequence;
        break;
    }
}

if (!$numeric_sequence) {
    foreach ($list as $sequence) {
        if (isset($sequence['DocumentType']) && $sequence['DocumentType'] === 0) {
            $numeric_sequence = $sequence;
            break;
        }
    }
}

if (!$numeric_sequence) {
    $numeric_sequence = $list[0]; // Fallback to first available
}

if (!$numeric_sequence || !isset($numeric_sequence['Id'])) {
    die("No IssuedInvoices numeric sequence found.\n");
}

$numeric_sequence_id = $numeric_sequence['Id'];

// Handle different field names for last number
$last_number = 0;
if (isset($numeric_sequence['LastNumber'])) {
    $last_number = intval($numeric_sequence['LastNumber']);
} elseif (isset($numeric_sequence['LastDocumentSerialNumber'])) {
    $last_number = intval($numeric_sequence['LastDocumentSerialNumber']);
}

$document_serial_number = $last_number + 1;

echo "✓ Using NumericSequenceId: $numeric_sequence_id\n";
echo "✓ Next DocumentSerialNumber: $document_serial_number\n\n";

// Step 4: Create IssuedInvoice
echo "Step 4: Creating IssuedInvoice...\n";

$year = date('Y');
$doc_num = $year . str_pad($document_serial_number, 4, '0', STR_PAD_LEFT);
$order_num = 'PO-' . $year . '-' . str_pad($document_serial_number, 2, '0', STR_PAD_LEFT);

$invoice_data = array(
    'PartnerId' => $partner_id ?: 22429105,
    'Description' => 'Consulting and license (API)',
    'Note' => 'Auto test via Postman',
    'OrderNumber' => $order_num,
    'VariableSymbol' => $doc_num,
    'DateOfIssue' => '2025-10-22',
    'DateOfTaxing' => '2025-10-22',
    'DateOfMaturity' => '2025-11-05',
    'DateOfAccountingEvent' => '2025-10-22',
    'DateOfVatApplication' => '2025-10-22',
    'CurrencyId' => 1,
    'ExchangeRate' => 1.0,
    'ExchangeRateAmount' => 1.0,
    'PaymentOptionId' => 1,
    'ConstantSymbolId' => 7,
    'NumericSequenceId' => $numeric_sequence_id,
    'DocumentSerialNumber' => $document_serial_number,
    'IsEet' => false,
    'EetResponsibility' => 0,
    'IsIncomeTax' => true,
    'VatOnPayStatus' => 0,
    'VatRegime' => 0,
    'HasVatRegimeOss' => false,
    'ItemsTextPrefix' => 'Invoice items:',
    'ItemsTextSuffix' => 'Thanks for your business.',
    'Items' => array(
        array(
            'Name' => 'Consulting service',
            'Unit' => 'hour',
            'Amount' => 2.0,
            'UnitPrice' => 1500.0,
            'PriceType' => 1,
            'VatRateType' => 2,
            'VatRate' => 0.0,
            'IsTaxMovement' => false,
            'DiscountPercentage' => 0.0
        )
    ),
    'ReportLanguage' => 1
);

$invoice_args = array(
    'method' => 'POST',
    'headers' => array(
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($invoice_data),
    'timeout' => 30
);

$invoice_response = wp_remote_request($base_url . '/IssuedInvoices', $invoice_args);

if (is_wp_error($invoice_response)) {
    die("Invoice creation failed: " . $invoice_response->get_error_message() . "\n");
}

$invoice_response_code = wp_remote_retrieve_response_code($invoice_response);
$invoice_response_body = wp_remote_retrieve_body($invoice_response);
$invoice_response_data = json_decode($invoice_response_body, true);

if (!in_array($invoice_response_code, array(200, 201))) {
    die("Invoice creation failed with status $invoice_response_code: $invoice_response_body\n");
}

// Handle different response structures
$invoice_id = null;
$invoice_number = null;

if (isset($invoice_response_data['Data'])) {
    $invoice_inner = $invoice_response_data['Data'];
} elseif (isset($invoice_response_data['data'])) {
    $invoice_inner = $invoice_response_data['data'];
} else {
    $invoice_inner = $invoice_response_data;
}

if (is_array($invoice_inner)) {
    if (isset($invoice_inner['Id'])) {
        $invoice_id = $invoice_inner['Id'];
    } elseif (isset($invoice_inner['id'])) {
        $invoice_id = $invoice_inner['id'];
    } elseif (isset($invoice_inner['Data']['Id'])) {
        $invoice_id = $invoice_inner['Data']['Id'];
    } elseif (isset($invoice_inner[0]['Id'])) {
        $invoice_id = $invoice_inner[0]['Id'];
    } elseif (isset($invoice_inner[0]['id'])) {
        $invoice_id = $invoice_inner[0]['id'];
    }
}

if (!$invoice_id && isset($invoice_response_data['Id'])) {
    $invoice_id = $invoice_response_data['Id'];
} elseif (!$invoice_id && isset($invoice_response_data['id'])) {
    $invoice_id = $invoice_response_data['id'];
}

if (is_array($invoice_inner)) {
    if (isset($invoice_inner['DocumentNumber'])) {
        $invoice_number = $invoice_inner['DocumentNumber'];
    } elseif (isset($invoice_inner['documentNumber'])) {
        $invoice_number = $invoice_inner['documentNumber'];
    } elseif (isset($invoice_inner['Data']['DocumentNumber'])) {
        $invoice_number = $invoice_inner['Data']['DocumentNumber'];
    } elseif (isset($invoice_inner[0]['DocumentNumber'])) {
        $invoice_number = $invoice_inner[0]['DocumentNumber'];
    } elseif (isset($invoice_inner[0]['documentNumber'])) {
        $invoice_number = $invoice_inner[0]['documentNumber'];
    }
}

if (!$invoice_number && isset($invoice_response_data['DocumentNumber'])) {
    $invoice_number = $invoice_response_data['DocumentNumber'];
} elseif (!$invoice_number && isset($invoice_response_data['documentNumber'])) {
    $invoice_number = $invoice_response_data['documentNumber'];
}

echo "✓ Created invoice id: $invoice_id, number: $invoice_number\n\n";

echo "=== SUCCESS ===\n";
echo "Invoice ID: $invoice_id\n";
echo "Invoice Number: $invoice_number\n";
echo "Status Code: $invoice_response_code\n\n";

echo "=== FULL RESPONSE ===\n";
echo json_encode($invoice_data, JSON_PRETTY_PRINT) . "\n";

echo "\n=== Test completed successfully ===\n";
