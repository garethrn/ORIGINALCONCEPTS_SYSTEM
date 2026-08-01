<?php
/**
 * QuickBooks API Helper Functions
 * Handles token refresh, API calls, and entity sync.
 */

if (!defined('OC_SYSTEM')) {
    die('Direct access not permitted.');
}

/**
 * Make an authenticated API request to QuickBooks.
 */
function qbApiRequest(string $method, string $endpoint, array $body = [], array $token = []): ?array
{
    $token = $token ?: (dbRow('SELECT * FROM qb_tokens ORDER BY created_at DESC LIMIT 1') ?? []);
    if (!$token) return null;

    // Refresh token if expired
    if (strtotime($token['expires_at']) <= time() + 60) {
        $token = refreshQbToken($token);
        if (!$token) return null;
    }

    $baseUrl = QB_BASE_URL . '/v3/company/' . $token['realm_id'];
    $url     = $baseUrl . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    $headers = [
        'Authorization: Bearer ' . $token['access_token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    $opts = [
        CURLOPT_URL            => $url . '?minorversion=' . QB_MINOR_VERSION,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method === 'GET') {
        $opts[CURLOPT_HTTPGET] = true;
    }

    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log('QB API cURL error: ' . $err);
        return null;
    }

    $decoded = json_decode($result, true);
    if ($code >= 400) {
        error_log('QB API error ' . $code . ': ' . $result);
        return null;
    }

    return $decoded;
}

/**
 * Refresh an expired access token.
 */
function refreshQbToken(array $token): ?array
{
    $ch = curl_init(QB_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode(QB_CLIENT_ID . ':' . QB_CLIENT_SECRET),
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);
    if (empty($data['access_token'])) return null;

    $expiresAt = date('Y-m-d H:i:s', time() + (int)($data['expires_in'] ?? 3600));
    dbUpdate('qb_tokens', [
        'access_token' => $data['access_token'],
        'expires_at'   => $expiresAt,
        'updated_at'   => date('Y-m-d H:i:s'),
    ], ['realm_id' => $token['realm_id']]);

    return array_merge($token, ['access_token' => $data['access_token'], 'expires_at' => $expiresAt]);
}

/**
 * Find or create a QuickBooks Customer by client.
 */
function qbFindOrCreateCustomer(array $client, array $token): ?string
{
    // Search for existing customer
    $query  = "SELECT * FROM Customer WHERE PrimaryEmailAddr = '" . addslashes($client['email'] ?? '') . "'";
    $result = qbApiRequest('GET', 'query?query=' . urlencode($query), [], $token);

    if (!empty($result['QueryResponse']['Customer'][0]['Id'])) {
        return $result['QueryResponse']['Customer'][0]['Id'];
    }

    // Create new customer
    $body = [
        'DisplayName' => $client['company'] ?: $client['name'],
        'GivenName'   => $client['name'],
        'CompanyName' => $client['company'] ?? '',
        'PrimaryEmailAddr' => ['Address' => $client['email'] ?? ''],
        'PrimaryPhone'     => ['FreeFormNumber' => $client['phone'] ?? ''],
        'BillAddr' => [
            'Line1'       => $client['address'] ?? '',
            'City'        => $client['city']    ?? '',
            'CountrySubDivisionCode' => $client['province'] ?? '',
            'PostalCode'  => $client['postal_code'] ?? '',
            'Country'     => $client['country']  ?? 'ZA',
        ],
    ];

    $result = qbApiRequest('POST', 'customer', $body, $token);
    return $result['Customer']['Id'] ?? null;
}

/**
 * Sync an invoice to QuickBooks.
 */
function syncInvoiceToQuickBooks(array $invoice, array $token): bool
{
    $client = dbRow('SELECT * FROM clients WHERE id = ?', [$invoice['client_id']]);
    if (!$client) return false;

    $customerId = qbFindOrCreateCustomer($client, $token);
    if (!$customerId) return false;

    $lineItems = json_decode($invoice['line_items'] ?? '[]', true) ?: [];
    $lines     = [];

    foreach ($lineItems as $item) {
        $lines[] = [
            'DetailType'        => 'SalesItemLineDetail',
            'Amount'            => (float)$item['total'],
            'Description'       => $item['desc'],
            'SalesItemLineDetail' => [
                'Qty'       => (float)$item['qty'],
                'UnitPrice' => (float)$item['rate'],
            ],
        ];
    }

    $body = [
        'CustomerRef'  => ['value' => $customerId],
        'Line'         => $lines,
        'DueDate'      => $invoice['due_date'],
        'TxnDate'      => date('Y-m-d', strtotime($invoice['created_at'])),
        'DocNumber'    => invoiceRef($invoice['id']),
        'GlobalTaxCalculation' => 'TaxExcluded',
    ];

    // If already has a QB ID, update instead
    if (!empty($invoice['qb_invoice_id'])) {
        $existing = qbApiRequest('GET', 'invoice/' . $invoice['qb_invoice_id'], [], $token);
        if ($existing) {
            $body['Id']       = $invoice['qb_invoice_id'];
            $body['SyncToken'] = $existing['Invoice']['SyncToken'] ?? '0';
        }
    }

    $result = qbApiRequest('POST', 'invoice', $body, $token);

    if (!empty($result['Invoice']['Id'])) {
        dbUpdate('invoices', [
            'qb_invoice_id' => $result['Invoice']['Id'],
            'updated_at'    => date('Y-m-d H:i:s'),
        ], ['id' => $invoice['id']]);
        return true;
    }

    return false;
}

/**
 * Sync a quote to QuickBooks as an Estimate.
 */
function syncEstimateToQuickBooks(array $quote, array $token): bool
{
    $client = dbRow('SELECT * FROM clients WHERE id = ?', [$quote['client_id']]);
    if (!$client) return false;

    $customerId = qbFindOrCreateCustomer($client, $token);
    if (!$customerId) return false;

    $lineItems = json_decode($quote['line_items'] ?? '[]', true) ?: [];
    $lines     = [];

    foreach ($lineItems as $item) {
        $lines[] = [
            'DetailType'           => 'SalesItemLineDetail',
            'Amount'               => (float)$item['total'],
            'Description'          => $item['desc'],
            'SalesItemLineDetail'  => [
                'Qty'       => (float)$item['qty'],
                'UnitPrice' => (float)$item['rate'],
            ],
        ];
    }

    $body = [
        'CustomerRef' => ['value' => $customerId],
        'Line'        => $lines,
        'TxnDate'     => date('Y-m-d', strtotime($quote['created_at'])),
        'DocNumber'   => quoteRef($quote['id']),
        'ExpirationDate' => $quote['valid_until'],
    ];

    $result = qbApiRequest('POST', 'estimate', $body, $token);

    if (!empty($result['Estimate']['Id'])) {
        dbUpdate('quotes', [
            'qb_estimate_id' => $result['Estimate']['Id'],
            'updated_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $quote['id']]);
        return true;
    }

    return false;
}
