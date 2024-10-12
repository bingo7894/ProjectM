<?php    
// เชื่อมต่อฐานข้อมูล
$host = 'db';
$user = 'webmaster';
$pass = 'P@ssw0rd';
$mydatabase = 'my_web';

$conn = new mysqli($host, $user, $pass, $mydatabase);

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับข้อมูลจาก ESP8266
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // ตรวจสอบข้อมูลที่ได้รับ
    if (isset($data['temperature'], $data['humidity'], $data['pm1'], $data['pm2_5'], $data['pm10'], $data['mq5'])) {
        $temperature = $data['temperature'];
        $humidity = $data['humidity'];
        $pm1 = $data['pm1'];
        $pm2_5 = $data['pm2_5'];
        $pm10 = $data['pm10'];
        $mq5 = $data['mq5'];

        // เตรียม SQL statement
        $stmt = $conn->prepare("INSERT INTO sensor_logs (temperature, humidity, pm1, pm2_5, pm10, mq5) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("dddddd", $temperature, $humidity, $pm1, $pm2_5, $pm10, $mq5);

        // เพิ่มข้อมูลลงฐานข้อมูล
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "New record created successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid input data."]);
    }
    exit();
}

// ฟังก์ชันสำหรับดาวน์โหลด CSV  
function downloadCSV($data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sensor_data.csv"');

    $output = fopen('php://output', 'w');
    
    // เขียน header ของ CSV
    fputcsv($output, ['ID', 'Temperature (°C)', 'Humidity (%)', 'PM1', 'PM2.5', 'PM10', 'Gas', 'Timestamp']);
    
    // เขียนข้อมูลแต่ละแถว
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// ตรวจสอบว่าเป็นการเรียกดูดาวน์โหลด CSV
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    $sql = "SELECT * FROM sensor_logs ORDER BY timestamp ASC"; // ดึงข้อมูลทั้งหมดเรียงตามเวลา (น้อยไปมาก)
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        downloadCSV($result->fetch_all(MYSQLI_ASSOC)); // ดึงข้อมูลทั้งหมดและส่งไปยังฟังก์ชัน
    } else {
        echo "No data available to download.";
    }
}

// ดึงข้อมูลจากฐานข้อมูลสำหรับกราฟและตาราง
$sql = "SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 10"; // ดึง 10 ค่าล่าสุด
$result = $conn->query($sql);

$timestamps = [];
$temperature = [];
$humidity = [];
$pm1 = [];
$pm2_5 = [];
$pm10 = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = $row['timestamp'];
        $temperature[] = $row['temperature'];
        $humidity[] = $row['humidity'];
        $pm1[] = $row['pm1'];
        $pm2_5[] = $row['pm2_5'];
        $pm10[] = $row['pm10'];
    }
}

// เพิ่มส่วนการดึงข้อมูลล่าสุด (Realtime)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['latest'])) {
    $sql = "SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 1";  // ดึงข้อมูลล่าสุด
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode($row);  // ส่งข้อมูลล่าสุดกลับไป
    } else {
        echo json_encode(['error' => 'No data found']);
    }
    exit();
}

$conn->close();
?>
