<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include 'config/db.php';

$data = file_get_contents("php://input");

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Không nhận được dữ liệu từ client.']);
    exit;
}

$cart = json_decode($data, true);

if (!is_array($cart)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit;
}

// Xử lý đơn hàng ở đây
foreach ($cart as $item) {
    $name = $item['name'];
    $quantity = (int)$item['quantity'];

    if ($quantity <= 0) {
        continue; // Bỏ qua nếu số lượng không hợp lệ
    }

    // Sử dụng Prepared Statement để bảo mật hơn
    $check_sql = "SELECT quantity FROM product WHERE name = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $name); // "s" là kiểu dữ liệu của tham số (string)
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_quantity = (int)$row['quantity'];

        if ($current_quantity < $quantity) {
            echo json_encode([
                'success' => false,
                'message' => "Sản phẩm '$name' không đủ hàng. Chỉ còn $current_quantity cái."
            ]);
            exit;
        }

        // Trừ số lượng trong kho
        $update_sql = "UPDATE product SET quantity = quantity - ? WHERE name = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("is", $quantity, $name); // "i" là kiểu int và "s" là kiểu string
        if (!$stmt->execute()) {
            echo json_encode([ 
                'success' => false, 
                'message' => 'Lỗi khi cập nhật sản phẩm: ' . $stmt->error 
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Sản phẩm '$name' không tồn tại trong kho."
        ]);
        exit;
    }
}

// Nếu thành công
echo json_encode(['success' => true, 'message' => 'Thanh toán thành công và đã cập nhật số lượng.']);
?>
