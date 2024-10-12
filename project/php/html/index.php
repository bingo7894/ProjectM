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


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor Data Chart</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .data-table th {
            background-color: #4CAF50;
            color: white;
        }
        footer {
    background-color: #333;
    color: #fff;
    padding: 30px 0;
    margin-top: 40px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    text-align: center;
}

.footer-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    max-width: 800px;
    margin: 0 auto;
    padding: 0 10px;
}

.footer-logo h3 {
    margin: 0;
    color: #4CAF50;
    font-size: 24px;
}

.footer-links a {
    color: #fff;
    margin: 0 15px;
    text-decoration: none;
    font-size: 16px;
}

.footer-links a:hover {
    color: #4CAF50;
    text-decoration: underline;
}

.footer-socials a {
    color: #fff;
    margin: 0 10px;
    font-size: 20px;
}

.footer-socials a:hover {
    color: #4CAF50;
}

.footer-bottom {
    border-top: 1px solid #444;
    padding-top: 10px;
    margin-top: 20px;
}

.footer-bottom a {
    color: #4CAF50;
    text-decoration: none;
}

.footer-bottom a:hover {
    text-decoration: underline;
}

    </style>
</head>
<body>
    <div class="container">
        <h2>Recent Sensor Data</h2>
        <button onclick="location.href='?download=csv'" class="download-button">ดาวน์โหลด CSV</button>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Temperature (°C)</th>
                    <th>Humidity (%)</th>
                    <th>PM1</th>
                    <th>PM2.5</th>
                    <th>PM10</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['timestamp']); ?></td>
                    <td><?php echo htmlspecialchars($row['temperature']); ?></td>
                    <td><?php echo htmlspecialchars($row['humidity']); ?></td>
                    <td><?php echo htmlspecialchars($row['pm1']); ?></td>
                    <td><?php echo htmlspecialchars($row['pm2_5']); ?></td>
                    <td><?php echo htmlspecialchars($row['pm10']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h1>Sensor Data Chart</h1>
        <canvas id="sensorChart" width="400" height="200"></canvas>
        
    </div>

    <script>
        // ข้อมูลจาก PHP มาใช้ใน JavaScript
        const data = {
            timestamps: <?php echo json_encode($timestamps); ?>,
            temperature: <?php echo json_encode($temperature); ?>,
            humidity: <?php echo json_encode($humidity); ?>,
            pm1: <?php echo json_encode($pm1); ?>,
            pm2_5: <?php echo json_encode($pm2_5); ?>
        };

        const ctx = document.getElementById('sensorChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.timestamps,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: data.temperature,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 4,
                    }, 
                    {
                        label: 'Humidity (%)',
                        data: data.humidity,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 4,
                    }, 
                    {
                        label: 'PM1',
                        data: data.pm1,
                        borderColor: 'rgba(75, 100, 10,1)',
                        backgroundColor: 'rgba(75, 100, 10,0.2)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 4,
                    },
                    {
                        label: 'PM2.5',
                        data: data.pm2_5,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 4,
                    }
                ]
            },
            options: {
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Timestamp',
                            color: '#333',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: true,
                            color: 'rgba(200, 200, 200, 0.3)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Values',
                            color: '#333',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: true,
                            color: 'rgba(200, 200, 200, 0.3)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#333',
                            boxWidth: 20
                        }
                    }
                }
            }
        });

        // สร้าง Legend สำหรับกราฟ
        const legendContainer = document.createElement('div');

       // ฟังก์ชันดึงข้อมูลล่าสุดจากเซิร์ฟเวอร์ (AJAX)
function fetchLatestData() {
    fetch('?latest=true')
        .then(response => response.json())
        .then(data => {
            // ตรวจสอบว่ามีข้อมูลใหม่
            if (!data.error) {
                // อัปเดตกราฟ
                chart.data.labels.push(data.timestamp);
                chart.data.datasets[0].data.push(data.temperature);
                chart.data.datasets[1].data.push(data.humidity);
                chart.data.datasets[2].data.push(data.pm1);
                chart.data.datasets[3].data.push(data.pm2_5);
                chart.update();

                // อัปเดตตาราง
                const tableBody = document.querySelector('.data-table tbody');
                
                // เพิ่มแถวใหม่ที่ด้านบนของตาราง
                const newRow = `
                    <tr>
                        <td>${data.timestamp}</td>
                        <td>${data.temperature}</td>
                        <td>${data.humidity}</td>
                        <td>${data.pm1}</td>
                        <td>${data.pm2_5}</td>
                        <td>${data.pm10}</td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('afterbegin', newRow); // เพิ่มแถวใหม่ที่ด้านบน

                // ตรวจสอบและลบแถวเก่าออกให้คงเหลือเพียง 10 แถว
                const rows = tableBody.querySelectorAll('tr');
                if (rows.length > 10) {
                    tableBody.removeChild(rows[rows.length - 1]); // ลบแถวที่เก่าสุด (แถวสุดท้าย)
                }
            }
        })
        .catch(error => console.log('Error fetching data:', error));
}

// อัปเดตข้อมูลทุก 5 วินาที
setInterval(fetchLatestData, 5000);

        document.querySelector('.container').appendChild(legendContainer);
    </script>
     <footer>
        <div class="footer-container">
            <div class="footer-logo">
                <h3>Sensor Monitoring System</h3>
            </div>
            <div class="footer-links">
                <a href="#">Home</a>
                <a href="#">About</a>
                <a href="#">Contact</a>
                <a href="#">Privacy Policy</a>
            </div>
            <div class="footer-socials">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Sensor Monitoring System | Designed by <a href="#">Your Name</a></p>
        </div>
    </footer>
</body>
</html>
