 
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli('localhost', 'kyle_user', 'Mesaboogie52!', 'books');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>CSV Import Tool</h1>";
echo "<p>Starting import process...</p>";

// Check if file exists
if (!file_exists('TitleList.csv')) {
    die("<p style='color:red'>Error: TitleList.csv file not found!</p>");
}

// Open the CSV file
$file = fopen('TitleList.csv', 'r');
if (!$file) {
    die("<p style='color:red'>Error: Could not open the CSV file!</p>");
}

// Get headers
$headers = fgetcsv($file);
echo "<p>CSV headers found: " . implode(", ", $headers) . "</p>";

// First, drop the existing table if it exists
$dropTable = "DROP TABLE IF EXISTS books";
if ($conn->query($dropTable)) {
    echo "<p>Existing table dropped successfully.</p>";
} else {
    echo "<p style='color:red'>Error dropping table: " . $conn->error . "</p>";
}

// Create table based on headers
$createTable = "CREATE TABLE books (";
foreach ($headers as $header) {
    // Make 'id' field an INT PRIMARY KEY
    if ($header == 'id') {
        $createTable .= "`" . $header . "` INT PRIMARY KEY, ";
    } else {
        $createTable .= "`" . $header . "` TEXT, ";
    }
}
$createTable = rtrim($createTable, ", ") . ")";

if ($conn->query($createTable)) {
    echo "<p>Table created successfully.</p>";
} else {
    echo "<p style='color:red'>Error creating table: " . $conn->error . "</p>";
}

// Insert data
$rowCount = 0;
$errorCount = 0;

while (($line = fgetcsv($file)) !== FALSE) {
    $rowCount++;
    
    // Skip if the line doesn't have the same number of columns as headers
    if (count($line) != count($headers)) {
        echo "<p style='color:orange'>Warning: Row $rowCount has " . count($line) . " columns (expected " . count($headers) . "). Skipping.</p>";
        $errorCount++;
        continue;
    }
    
    $sql = "INSERT INTO books VALUES (";
    foreach ($line as $value) {
        $sql .= "'" . $conn->real_escape_string($value) . "', ";
    }
    $sql = rtrim($sql, ", ") . ")";
    
    if (!$conn->query($sql)) {
        echo "<p style='color:red'>Error on row $rowCount: " . $conn->error . "</p>";
        $errorCount++;
    }
}

fclose($file);

echo "<h2>Import Summary</h2>";
echo "<p>Total rows processed: $rowCount</p>";
echo "<p>Errors encountered: $errorCount</p>";
echo "<p style='color:green;font-weight:bold'>Import completed!</p>";
echo "<p><a href='bookreviews.php'>View Books</a></p>";
?>
