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
$checkSql = "SELECT id FROM schools WHERE CenterNo = ?"; 
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    die("Prepare failed: " . $conn->error);
}

// Prepare SQL for inserting new records
$insertSql = "INSERT IGNORE INTO schools (CenterNo, School_Name) VALUES (?, ?)"; // Simplified to only handle CenterNo and School_Name
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    die("Prepare failed: " . $conn->error);
}

// School data
$data = [
    ['002123', 'KIMANTO PRIMARY SCHOOL'],
    ['002124', 'BUDONDO PRIMARY SCHOOL'],
    ['002126', 'LUKUNHU MOSLEM PRIMARY SCHOOL'],
    ['002127', 'BUSALAMU PRIMARY SCHOOL'],
    ['002128', 'TABINGWA PRIMARY SCHOOL'],
    ['002130', 'BUWOLOGOMA PRIMARY SCHOOL'],
    ['002131', 'BUKADDE PRIMARY SCHOOL'],
    ['002132', 'NDHOYA PRIMARY SCHOOL'],
    ['002134', 'BIGUNHO PRIMARY SCHOOL'],
    ['002135', 'KIROBA PRIMARY SCHOOL'],
    ['002136', 'NAKABONDO PRIMARY SCHOOL'],
    ['002137', 'BUDOMA PRIMARY SCHOOL'],
    ['002138', 'WALYEMBWA PRIMARY SCHOOL'],
    ['002139', 'BUKANGA PRIMARY SCHOOL'],
    ['002140', 'NAMUKUBEMBE PRIMARY SCHOOL'],
    ['002141', 'BUKANHA PRIMARY SCHOOL'],
    ['002142', 'BUKYANGWA PRIMARY SCHOOL'],
    ['002143', 'BUKOOVA ST.MARY\'S PRI. SCHOOL'],
    ['002144', 'BUSANDA PRIMARY SCHOOL'],
    ['002145', 'ST.THOMAS MUKUTU PRI. SCHOOL'],
    ['002147', 'NAIRIKA PRIMARY SCHOOL'],
    ['002148', 'NAIGOBYA PRIMARY SCHOOL'],
    ['002149', 'BUSAKU PRIMARY SCHOOL'],
    ['002150', 'KIRIMWA PRIMARY SCHOOL'],
    ['002151', 'NAMULANDA PRIMARY SCHOOL'],
    ['002152', 'GWEMBUZI PRIMARY SCHOOL'],
    ['002153', 'NAWANSEGA PRIMARY SCHOOL'],
    ['002157', 'NAMUMERA PRIMARY SCHOOL'],
    ['002158', 'BUGONYOKA PRIMARY SCHOOL'],
    ['002160', 'NABITAAMA PRIMARY SCHOOL'],
    ['002161', 'BUKENDI PRIMARY SCHOOL'],
    ['002162', 'BUGABULA PRIMARY SCHOOL'],
    ['002163', 'MAWEMBE PRIMARY SCHOOL'],
    ['002165', 'KIYUNGA PRIMARY SCHOOL'],
    ['002166', 'KAMWIRUNGU PRIMARY SCHOOL'],
    ['002167', 'KITWEKYAMBOGO PRIMARY SCHOOL'],
    ['002170', 'BUYUNZE PRIMARY SCHOOL'],
    ['002171', 'BUSALA PRIMARY SCHOOL'],
    ['002172', 'NAKABUGU PRIMARY SCHOOL'],
    ['002173', 'IKUMBYA PRIMARY SCHOOL'],
    ['002174', 'IKUMBYA CATHOLIC PRI. SCHOOL'],
    ['002176', 'BUGAMBO PRIMARY SCHOOL'],
    ['002177', 'BUDHUUBA PRIMARY SCHOOL'],
    ['002178', 'WANDAGO PRIMARY SCHOOL'],
    ['002179', 'BUNAFU PRIMARY SCHOOL'],
    ['002180', 'NAWAKA PRIMARY SCHOOL'],
    ['002181', 'NTAYIGIRWA PRIMARY SCHOOL'],
    ['002182', 'BUKOBBO PRIMARY SCHOOL'],
    ['002183', 'IRONGO PRIMARY SCHOOL'],
    ['002184', 'LAMBALA PRIMARY SCHOOL'],
    ['002185', 'NAIMULI PRIMARY SCHOOL'],
    ['002187', 'KALYOWA PRIMARY SCHOOL'],
    ['002188', 'NAKAVUMA PRIMARY SCHOOL'],
    ['002189', 'NKANDAKULYOWA PRIMARY SCHOOL'],
    ['002191', 'KIWALAZI PRIMARY SCHOOL'],
    ['002192', 'NAKABAALE PRIMARY SCHOOL'],
    ['002194', 'BUTOGONYA PRIMARY SCHOOL'],
    ['002195', 'BUYEMBA PRIMARY SCHOOL'],
    ['002196', 'BUWANDA PRIMARY SCHOOL'],
    ['002198', 'BUGOMBA PRIMARY SCHOOL'],
    ['002199', 'BUYOOLA PRIMARY SCHOOL'],
    ['002200', 'IKONIA PRIMARY SCHOOL'],
    ['002202', 'NABIKUYI PRIMARY SCHOOL'],
    ['002203', 'NAMAGERA PRIMARY SCHOOL'],
    ['002204', 'NAWAMPITI PRIMARY SCHOOL'],
    ['002205', 'KITUUTO PRIMARY SCHOOL'],
    ['002208', 'NAWANKOMPE PRIMARY SCHOOL'],
    ['002209', 'BUSIIRO PRIMARY SCHOOL'],
    ['002211', 'BUSIIRO MUSLIM PRIMARY SCHOOL'],
    ['002212', 'BUTIMBWA PRIMARY SCHOOL'],
    ['002213', 'NAMAKAKALE PRIMARY SCHOOL'],
    ['002214', 'WAIBUGA MUSLIM PRIMARY SCHOOL'],
    ['002216', 'WAIBUGA PRIMARY SCHOOL'],
    ['002217', 'BUWIIRI PRIMARY SCHOOL'],
    ['002218', 'KAKUMBI PRIMARY SCHOOL'],
    ['002219', 'NAMADOPE PRIMARY SCHOOL'],
    ['002221', 'BULANGA PRIMARY SCHOOL'],
    ['002222', 'MAUNDO PRIMARY SCHOOL'],
    ['002223', 'BULANGA NAHADHAT PRI. SCHOOL'],
    ['002225', 'WALIBO PRIMARY SCHOOL'],
    ['080012', 'BUDHAANA PRIMARY SCHOOL'],
    ['080013', 'KIYUNGA PARENTS PRIMARY SCHOOL'],
    ['080048', 'BUYOGA PRIMARY SCHOOL'],
    ['080071', 'BULAWA PRIMARY SCHOOL'],
    ['080072', 'KYANVUMA PRIMARY SCHOOL'],
    ['080073', 'BUGONZA PRIMARY SCHOOL'],
    ['080079', 'ROADSIDE PRIMARY SCHOOL'],
    ['080093', 'ST.KIZITO KAWANGA P/S'],
    ['080095', 'NABYOTO PRIMARY SCHOOL'],
    ['080104', 'ST.ALICE BUDOMA STAR PRIMARY SCHOOL'],
    ['080125', 'NABIMOGO PRIMARY SCHOOL'],
    ['080152', 'SAAWE VICTORY PRIMARY SCHOOL'],
    ['950002', 'IKONIA PARENTS PRIMARY SCHOOL'],
    ['950003', 'MARIAM MEMORIAL PRIMARY SCHOOL'],
    ['950054', 'HEALING CHILDREN SCHOOL'],
    ['950063', 'NEW HOPE PRIMARY SCHOOL'],
    ['950069', 'NAMULANDA MODEL PRIMARY SCHOOL'],
    ['950073', 'BUDHUUBA MODERN PRIMARY SCHOOL'],
    ['950080', 'SHALOM CHRISTIAN BOARDING P/S'],
    ['950081', 'B. M PRIMARY SCHOOL'],
    ['950094', 'BULIKE COMMUNITY SCHOOL'],
    ['950101', 'ST.ALOYSIOUS PRIMARY SCHOOL,LUUKA'],
    ['950180', 'KIYUNGA VILLAGE PRIMARY SCHOOL']
];


// Process each school entry
foreach ($data as $row) {
    $centerNo = $row[0];
    $schoolName = $row[1];

    // Check if record exists
    $checkStmt->bind_param('s', $centerNo);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        // Insert new record if it doesn't exist
        $insertStmt->bind_param('ss', $centerNo, $schoolName);
        if (!$insertStmt->execute()) {
            if ($conn->errno == 1062) { // Duplicate entry error
                echo "Duplicate entry for CenterNo $centerNo<br>";
            } else {
                echo "Insert failed for CenterNo $centerNo: " . $insertStmt->error . "<br>";
            }
        } else {
            echo "Inserted CenterNo $centerNo<br>"; // Debugging output
        }
    } else {
        echo "CenterNo $centerNo already exists<br>"; // Debugging output
    }

    $checkStmt->free_result();
}

// Close statements and connection
$checkStmt->close();
$insertStmt->close();
$conn->close();

echo "Data import completed.";
?>
