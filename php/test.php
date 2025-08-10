<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Prepare SQL for checking existing records
$checkSql = "SELECT id FROM schools WHERE CenterNo = ?"; // Adjust table name if needed
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    die("Prepare failed: " . $conn->error);
}

// Prepare SQL for inserting new records
$insertSql = "INSERT INTO schools (CenterNo, School_Name, Sub_county, School_type) VALUES (?, ?, ?, ?)";
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    die("Prepare failed: " . $conn->error);
}

// School data
$data = [
    ['002123', 'KIMANTO PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002124', 'BUDONDO PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002126', 'LUKUNHU MOSLEM PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002127', 'BUSALAMU PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002128', 'TABINGWA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002130', 'BUWOLOGOMA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002131', 'BUKADDE PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002132', 'NDHOYA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002134', 'BIGUNHO PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002135', 'KIROBA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002136', 'NAKABONDO PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002137', 'BUDOMA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002138', 'WALYEMBWA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002139', 'BUKANGA PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002140', 'NAMUKUBEMBE PRIMARY SCHOOL', 'Bukanga', 'Government Aided'],
    ['002141', 'BUKANHA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002142', 'BUKYANGWA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002143', 'BUKOOVA ST.MARY\'S PRI. SCHOOL', 'Bukoma', 'Government Aided'],
    ['002144', 'BUSANDA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002145', 'ST.THOMAS MUKUTU PRI. SCHOOL', 'Bukoma', 'Government Aided'],
    ['002147', 'NAIRIKA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002148', 'NAIGOBYA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002149', 'BUSAKU PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002150', 'KIRIMWA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002151', 'NAMULANDA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002152', 'GWEMBUZI PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002153', 'NAWANSEGA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['002157', 'NAMUMERA PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002158', 'BUGONYOKA PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002160', 'NABITAAMA PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002161', 'BUKENDI PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002162', 'BUGABULA PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002163', 'MAWEMBE PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002165', 'KIYUNGA PRIMARY SCHOOL', 'Luuka Town Council', 'Government Aided'],
    ['002166', 'KAMWIRUNGU PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002167', 'KITWEKYAMBOGO PRIMARY SCHOOL', 'Luuka Town Council', 'Government Aided'],
    ['002170', 'BUYUNZE PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002171', 'BUSALA PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002172', 'NAKABUGU PRIMARY SCHOOL', 'Bulongo', 'Government Aided'],
    ['002173', 'IKUMBYA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002174', 'IKUMBYA CATHOLIC PRI. SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002176', 'BUGAMBO PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002177', 'BUDHUUBA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002178', 'WANDAGO PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002179', 'BUNAFU PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002180', 'NAWAKA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002181', 'NTAYIGIRWA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002182', 'BUKOBBO PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['002183', 'IRONGO PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002184', 'LAMBALA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002185', 'NAIMULI PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002187', 'KALYOWA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002188', 'NAKAVUMA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002189', 'NKANDAKULYOWA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002191', 'KIWALAZI PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002192', 'NAKABAALE PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002194', 'BUTOGONYA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['002195', 'BUYEMBA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002196', 'BUWANDA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002198', 'BUGOMBA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002199', 'BUYOOLA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002200', 'IKONIA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002202', 'NABIKUYI PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002203', 'NAMAGERA PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002204', 'NAWAMPITI PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002205', 'KITUUTO PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002208', 'NAWANKOMPE PRIMARY SCHOOL', 'Nawampiti', 'Government Aided'],
    ['002209', 'BUSIIRO PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002211', 'BUSIIRO MUSLIM PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002212', 'BUTIMBWA PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002213', 'NAMAKAKALE PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002214', 'WAIBUGA MUSLIM PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002216', 'WAIBUGA PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002217', 'BUWIIRI PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002218', 'KAKUMBI PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002219', 'NAMADOPE PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002221', 'BULANGA PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002222', 'MAUNDO PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['002223', 'BULANGA NAHADHAT PRI. SCHOOL', 'Waibuga', 'Private'],
    ['002225', 'WALIBO PRIMARY SCHOOL', 'Waibuga', 'Government Aided'],
    ['080012', 'BUDHAANA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['080013', 'KIYUNGA PARENTS PRIMARY SCHOOL', 'Luuka Town Council', 'Private'],
    ['080048', 'BUYOGA PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['080071', 'BULAWA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['080072', 'KYANVUMA PRIMARY SCHOOL', 'Irongo', 'Government Aided'],
    ['080073', 'BUGONZA PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['080079', 'ROADSIDE PRIMARY SCHOOL', 'Waibuga', 'Private'],
    ['080093', 'ST.KIZITO KAWANGA P/S', 'Ikumbya', 'Government Aided'],
    ['080095', 'NABYOTO PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['080104', 'ST.ALICE BUDOMA STAR PRIMARY SCHOOL', 'Bukanga', 'Private'],
    ['080125', 'NABIMOGO PRIMARY SCHOOL', 'Bukoma', 'Government Aided'],
    ['080152', 'SAAWE VICTORY PRIMARY SCHOOL', 'Waibuga', 'Private'],
    ['950002', 'IKONIA PARENTS PRIMARY SCHOOL', 'Nawampiti', 'Private'],
    ['950003', 'MARIAM MEMORIAL PRIMARY SCHOOL', 'Waibuga', 'Private'],
    ['950054', 'HEALING CHILDREN SCHOOL', 'Waibuga', 'Private'],
    ['950063', 'NEW HOPE PRIMARY SCHOOL', 'Bukanga', 'Private'],
    ['950069', 'NAMULANDA MODEL PRIMARY SCHOOL', 'Bukoma', 'Private'],
    ['950073', 'BUDHUUBA MODERN PRIMARY SCHOOL', 'Ikumbya', 'Government Aided'],
    ['950080', 'SHALOM CHRISTIAN BOARDING P/S', 'Bukanga', 'Private'],
    ['950081', 'B. M PRIMARY SCHOOL', 'Luuka Town Council', 'Private'],
    ['950094', 'BULIKE COMMUNITY SCHOOL', 'Ikumbya', 'Private'],
    ['950101', 'ST.ALOYSIOUS PRIMARY SCHOOL,LUUKA', 'Irongo', 'Private'],
    ['950180', 'KIYUNGA VILLAGE PRIMARY SCHOOL', 'Luuka Town Council', 'Private']
];

// Process each school entry
foreach ($data as $row) {
    $centerNo = $row[0];
    $schoolName = $row[1];
    $subCounty = $row[2];
    $schoolType = $row[3];

    // Check if record exists
    $checkStmt->bind_param('s', $centerNo);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        // Insert new record if it doesn't exist
        $insertStmt->bind_param('ssss', $centerNo, $schoolName, $subCounty, $schoolType);
        if (!$insertStmt->execute()) {
            echo "Insert failed for CenterNo $centerNo: " . $insertStmt->error . "<br>";
        }
    }

    $checkStmt->free_result();
}

// Close statements and connection
$checkStmt->close();
$insertStmt->close();
$conn->close();

echo "Data import completed.";
?>
