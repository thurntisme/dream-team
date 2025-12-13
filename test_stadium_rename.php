<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    die('Database not available');
}

if (!isset($_SESSION['user_id'])) {
    die('User not logged in');
}

try {
    $db = getDbConnection();
    $user_id = $_SESSION['user_id'];

    echo "<h2>Stadium Rename Test</h2>";
    echo "<p>User ID: " . $user_id . "</p>";

    // Check current stadium name change items
    echo "<h3>Current Stadium Name Change Items:</h3>";
    $stmt = $db->prepare('SELECT ui.*, si.name FROM user_inventory ui 
                         JOIN shop_items si ON ui.item_id = si.id 
                         WHERE ui.user_id = :user_id AND si.effect_type = "stadium_rename"');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $total_quantity = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<p>Item: " . $row['name'] . ", Quantity: " . $row['quantity'] . ", Purchased: " . $row['purchased_at'] . "</p>";
        $total_quantity += $row['quantity'];
    }

    echo "<p><strong>Total Stadium Name Change items: " . $total_quantity . "</strong></p>";

    if ($total_quantity > 0) {
        echo "<p style='color: green;'>✅ You can rename your stadium!</p>";
        echo "<p><a href='stadium.php'>Go to Stadium Page</a></p>";
    } else {
        echo "<p style='color: red;'>❌ You need to purchase Stadium Name Change item from the shop.</p>";
        echo "<p><a href='shop.php'>Go to Shop</a></p>";
    }

    // Show current stadium name
    $stmt = $db->prepare('SELECT name FROM stadiums WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $stadium = $result->fetchArray(SQLITE3_ASSOC);

    if ($stadium) {
        echo "<h3>Current Stadium Name:</h3>";
        echo "<p><strong>" . htmlspecialchars($stadium['name']) . "</strong></p>";
    }

    $db->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h2,
    h3 {
        color: #333;
    }

    p {
        margin: 10px 0;
    }

    a {
        color: #007cba;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
</style>