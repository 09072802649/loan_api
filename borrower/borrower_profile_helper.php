<?php

require_once dirname(__DIR__) . '/loan_account_helper.php';

function ensureBorrowerProfileImageColumn(mysqli $conn): bool
{
    return borrowerTableHasColumn($conn, 'users', 'profile_image');
}

function borrowerReadJsonInput(): array
{
    static $cachedInput = null;

    if ($cachedInput !== null) {
        return $cachedInput;
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        $cachedInput = [];
        return $cachedInput;
    }

    $decoded = json_decode($rawBody, true);
    $cachedInput = is_array($decoded) ? $decoded : [];

    return $cachedInput;
}

function borrowerRequestValue(string $key, $default = null)
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }

    if (isset($_GET[$key])) {
        return $_GET[$key];
    }

    $jsonInput = borrowerReadJsonInput();
    if (array_key_exists($key, $jsonInput)) {
        return $jsonInput[$key];
    }

    return $default;
}

function borrowerRequestInt(string $key): int
{
    return (int)borrowerRequestValue($key, 0);
}

function borrowerGetTableColumns(mysqli $conn, string $table): array
{
    if (!isset($GLOBALS['borrower_table_columns_cache']) || !is_array($GLOBALS['borrower_table_columns_cache'])) {
        $GLOBALS['borrower_table_columns_cache'] = [];
    }

    $cache = &$GLOBALS['borrower_table_columns_cache'];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $cache[$table] = [];
    $result = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if (!$result) {
        return $cache[$table];
    }

    while ($row = $result->fetch_assoc()) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $cache[$table][$field] = true;
        }
    }

    return $cache[$table];
}

function borrowerClearTableColumnsCache(string $table): void
{
    if (isset($GLOBALS['borrower_table_columns_cache'][$table])) {
        unset($GLOBALS['borrower_table_columns_cache'][$table]);
    }
}

function borrowerTableHasColumn(mysqli $conn, string $table, string $column): bool
{
    $columns = borrowerGetTableColumns($conn, $table);
    return isset($columns[strtolower($column)]);
}

function borrowerGetProfileImageIndexPath(): string
{
    return dirname(__DIR__) . '/uploads/profile/profile_image_index.json';
}

function borrowerGetProfilesDirectoryPath(): string
{
    return dirname(__DIR__) . '/Profiles';
}

function borrowerGetStoredProfileImagePath(int $borrowerId): string
{
    return 'Profiles/profile_' . $borrowerId . '.jpg';
}

function borrowerEnsureProfilesDirectory(): bool
{
    $directory = borrowerGetProfilesDirectoryPath();
    return is_dir($directory) || (mkdir($directory, 0777, true) || is_dir($directory));
}

function borrowerReadProfileImageIndex(): array
{
    $indexPath = borrowerGetProfileImageIndexPath();
    if (!is_file($indexPath)) {
        return [];
    }

    $contents = @file_get_contents($indexPath);
    if (!is_string($contents) || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function borrowerWriteProfileImageIndex(array $data): bool
{
    $indexPath = borrowerGetProfileImageIndexPath();
    $directory = dirname($indexPath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        return false;
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents($indexPath, $encoded, LOCK_EX) !== false;
}

function borrowerFetchUserRecord(mysqli $conn, int $borrowerId): ?array
{
    $selectFields = [
        'id',
        'fullname',
        'email',
        borrowerTableHasColumn($conn, 'users', 'phone') ? 'phone' : "'' AS phone",
        borrowerTableHasColumn($conn, 'users', 'address') ? 'address' : "'' AS address",
        borrowerTableHasColumn($conn, 'users', 'status') ? 'status AS user_status' : "'' AS user_status",
        borrowerTableHasColumn($conn, 'users', 'profile_image') ? 'profile_image' : "'' AS profile_image",
    ];

    $sql = "
        SELECT " . implode(",\n               ", $selectFields) . "
        FROM users
        WHERE id = ?
          AND role = 'borrower'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to load borrower profile');
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function borrowerFetchLatestLoan(mysqli $conn, int $borrowerId): ?array
{
    $selectFields = [
        'id AS loan_id',
        borrowerTableHasColumn($conn, 'loans', 'loan_account_number') ? 'loan_account_number' : "'' AS loan_account_number",
        borrowerTableHasColumn($conn, 'loans', 'status') ? 'status AS loan_status' : "'' AS loan_status",
    ];

    $orderParts = [];
    if (borrowerTableHasColumn($conn, 'loans', 'status')) {
        $orderParts[] = "
            CASE
                WHEN status = 'ongoing' THEN 0
                WHEN status = 'approved' THEN 1
                WHEN status = 'pending' THEN 2
                WHEN status = 'completed' THEN 3
                ELSE 4
            END
        ";
    }
    if (borrowerTableHasColumn($conn, 'loans', 'created_at')) {
        $orderParts[] = 'created_at DESC';
    }
    $orderParts[] = 'id DESC';

    $sql = "
        SELECT " . implode(",\n               ", $selectFields) . "
        FROM loans
        WHERE borrower_id = ?
        ORDER BY " . implode(",\n                 ", $orderParts) . "
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $loan = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $loan ?: null;
}

function borrowerFormatAccountStatus(array $user, ?array $loan): string
{
    $userStatus = strtolower(trim((string)($user['user_status'] ?? '')));
    if ($userStatus !== '') {
        return ucwords(str_replace('_', ' ', $userStatus));
    }

    $loanStatus = strtolower(trim((string)($loan['loan_status'] ?? '')));
    if ($loanStatus === 'ongoing' || $loanStatus === 'approved') {
        return 'Active';
    }
    if ($loanStatus !== '') {
        return ucwords(str_replace('_', ' ', $loanStatus));
    }

    return 'Active';
}

function borrowerResolveProfileImage(array $user): string
{
    $profileImage = trim((string)($user['profile_image'] ?? ''));
    if ($profileImage !== '') {
        return $profileImage;
    }

    $borrowerId = (int)($user['id'] ?? 0);
    if ($borrowerId <= 0) {
        return '';
    }

    $index = borrowerReadProfileImageIndex();
    $storedPath = trim((string)($index[(string)$borrowerId] ?? ''));

    return $storedPath;
}

function borrowerPersistProfileImage(mysqli $conn, int $borrowerId, string $profileImagePath): bool
{
    $profileImagePath = trim($profileImagePath);
    if ($profileImagePath === '') {
        return false;
    }

    if (!ensureBorrowerProfileImageColumn($conn) || !borrowerTableHasColumn($conn, 'users', 'profile_image')) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $profileImagePath, $borrowerId);
    $updated = $stmt->execute();
    $stmt->close();

    return $updated;
}

function borrowerFetchProfilePayload(mysqli $conn, int $borrowerId): ?array
{
    $user = borrowerFetchUserRecord($conn, $borrowerId);
    if (!$user) {
        return null;
    }

    $loan = borrowerFetchLatestLoan($conn, $borrowerId);
    $accountStatus = borrowerFormatAccountStatus($user, $loan);
    $resolvedLoanAccountNumber = $loan ? resolveLoanAccountNumber($loan) : '';

    return [
        'borrower_id' => $borrowerId,
        'fullname' => (string)($user['fullname'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'phone' => (string)($user['phone'] ?? ''),
        'address' => (string)($user['address'] ?? ''),
        'loan_account_number' => $resolvedLoanAccountNumber !== '' ? $resolvedLoanAccountNumber : (string)($loan['loan_account_number'] ?? ''),
        'status' => $accountStatus,
        'account_status' => $accountStatus,
        'user_status' => $accountStatus,
        'profile_image' => borrowerResolveProfileImage($user),
    ];
}
