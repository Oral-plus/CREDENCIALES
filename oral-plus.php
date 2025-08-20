<?php
// Database connection parameters
$serverName = "HERCULES"; // e.g., "localhost\SQLEXPRESS"
$connectionOptions = array(
    "Database" => "CREDENCIALES",
    "Uid" => "SA",
    "PWD" => "Sky2022*!"
);

// Connect to SQL Server
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Handle form submission for updating records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $cargo = filter_input(INPUT_POST, 'cargo', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $contrasena = filter_input(INPUT_POST, 'contrasena', FILTER_SANITIZE_STRING);

    if ($id && $nombre && $cargo && $email && $contrasena) {
        $sql = "UPDATE [CREDENCIALES].[dbo].[Credenciales] 
                SET Nombre = ?, Cargo = ?, Email = ?, Contrasena = ? 
                WHERE Id = ?";
        $params = array($nombre, $cargo, $email, $contrasena, $id);
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>Error updating record: " . print_r(sqlsrv_errors(), true) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        } else {
            echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>Record updated successfully.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        sqlsrv_free_stmt($stmt);
    } else {
        echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>Please fill all fields correctly.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $sql = "SELECT [Id], [Nombre], [Cargo], [Email], [Contrasena] 
            FROM [CREDENCIALES].[dbo].[Credenciales]";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $temp_file = tempnam(sys_get_temp_dir(), 'xlsx');
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
        <sheetData>
            <row r="1">
                <c r="A1" t="s"><v>0</v></c>
                <c r="B1" t="s"><v>1</v></c>
                <c r="C1" t="s"><v>2</v></c>
                <c r="D1" t="s"><v>3</v></c>
                <c r="E1" t="s"><v>4</v></c>
            </row>';

    $sharedStrings = ['Id', 'Nombre', 'Cargo', 'Email', 'Contrasena'];
    $rowIndex = 2;

    while ($data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $xml .= "<row r=\"$rowIndex\">";
        $xml .= "<c r=\"A$rowIndex\"><v>" . htmlspecialchars($data['Id']) . "</v></c>";
        $xml .= "<c r=\"B$rowIndex\" t=\"s\"><v>" . (count($sharedStrings)) . "</v></c>";
        $sharedStrings[] = $data['Nombre'];
        $xml .= "<c r=\"C$rowIndex\" t=\"s\"><v>" . (count($sharedStrings)) . "</v></c>";
        $sharedStrings[] = $data['Cargo'];
        $xml .= "<c r=\"D$rowIndex\" t=\"s\"><v>" . (count($sharedStrings)) . "</v></c>";
        $sharedStrings[] = $data['Email'];
        $xml .= "<c r=\"E$rowIndex\" t=\"s\"><v>" . (count($sharedStrings)) . "</v></c>";
        $sharedStrings[] = $data['Contrasena'];
        $xml .= "</row>";
        $rowIndex++;
    }
    $xml .= '</sheetData></worksheet>';

    $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $string) {
        $sharedStringsXml .= '<si><t>' . htmlspecialchars($string) . '</t></si>';
    }
    $sharedStringsXml .= '</sst>';

    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Cannot open ZIP file");
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
        <Default Extension="xml" ContentType="application/xml"/>
        <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
        <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    </Types>');

    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
        <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
    </Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
        <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
    </workbook>');

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="credenciales_export.xlsx"');
    header('Cache-Control: max-age=0');
    readfile($temp_file);
    unlink($temp_file);
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    exit;
}

// Count total records first
$countSql = "SELECT COUNT(*) as total FROM [CREDENCIALES].[dbo].[Credenciales]";
$countStmt = sqlsrv_query($conn, $countSql);
if ($countStmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
$countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
$totalRecords = $countRow['total'];
sqlsrv_free_stmt($countStmt);

// Fetch data for display
$sql = "SELECT [Id], [Nombre], [Cargo], [Email], [Contrasena] 
        FROM [CREDENCIALES].[dbo].[Credenciales]";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Credenciales</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/8115/8115340.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-pink: #1ed1e9ff;
            --light-pink: #e4f5fcff;
            --medium-pink: #bbdaf8ff;
            --dark-pink: #1895c2ff;
            --accent-pink: #408cffff;
            --soft-pink: #f3e5f5;
            --text-dark: #2d3748;
            --text-light: #718096;
            --border-light: #e2e8f0;
            --shadow-color: rgba(30, 135, 233, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--soft-pink) 0%, #fdf2f8 50%, var(--light-pink) 100%);
            color: var(--text-dark);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            padding: 40px 30px;
            background: linear-gradient(135deg, white 0%, var(--light-pink) 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow-color);
            border: 1px solid var(--medium-pink);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, var(--accent-pink) 0%, transparent 70%);
            opacity: 0.1;
            z-index: 1;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .header h1 {
            font-size: 3.2em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 15px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header p {
            color: var(--text-light);
            font-size: 1.2em;
            margin: 0;
            font-weight: 400;
        }

        .stats-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            gap: 30px;
            flex-wrap: wrap;
        }

        .stats-card {
            background: linear-gradient(135deg, white 0%, var(--light-pink) 100%);
            padding: 25px 35px;
            border-radius: 16px;
            box-shadow: 0 8px 25px var(--shadow-color);
            border: 1px solid var(--medium-pink);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px var(--shadow-color);
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4em;
        }

        .stats-info h3 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-pink);
        }

        .stats-info p {
            margin: 0;
            color: var(--text-light);
            font-size: 0.95em;
        }

        .btn-export {
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px var(--shadow-color);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow-color);
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow-color);
            padding: 0;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            padding: 25px 30px;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .table-header h2 {
            margin: 0;
            font-size: 1.5em;
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
            margin: 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: white;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--medium-pink), var(--light-pink));
            color: var(--dark-pink);
            padding: 20px 25px;
            text-align: left;
            font-weight: 600;
            font-size: 1em;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody td {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--light-pink);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .password-field {
            color: var(--text-dark);
            font-family: 'Courier New', monospace;
            font-size: 0.95em;
            font-weight: 500;
            background-color: var(--light-pink);
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-block;
            min-width: 100px;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.2);
        }

        .btn-edit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.3);
        }

        .modal-content {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            color: white;
            border-bottom: none;
            padding: 25px 30px;
        }

        .modal-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.4em;
        }

        .modal-header .btn-close {
            background: white;
            opacity: 1;
            border-radius: 50%;
            width: 32px;
            height: 32px;
        }

        .modal-body {
            padding: 40px 30px;
        }

        .modal-footer {
            padding: 25px 30px;
            border-top: 1px solid var(--border-light);
            background: var(--light-pink);
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid var(--border-light);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
            background: white;
        }

        .mb-3 {
            margin-bottom: 25px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-pink), var(--primary-pink));
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--text-light);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: var(--text-dark);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 2.2em;
            }

            .stats-section {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-card {
                min-width: auto;
            }

            .table-wrapper {
                margin: 0 -15px;
            }

            .table thead th,
            .table tbody td {
                padding: 15px 10px;
                font-size: 0.9em;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .modal-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-shield-alt"></i> Gestión de Credenciales</h1>
                <p>Administra de forma segura las credenciales del sistema con estilo y elegancia</p>
            </div>
        </div>

        <div class="stats-section">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-info">
                    <h3><?php echo $totalRecords; ?></h3>
                    <p>Registros totales</p>
                </div>
            </div>
            
            <a href="?export=xlsx" class="btn-export">
                <i class="fas fa-file-excel"></i>
                Exportar a Excel
            </a>
        </div>

        <div class="table-container">
            <div class="table-header">
                <i class="fas fa-table"></i>
                <h2>Lista de Credenciales</h2>
            </div>
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Nombre</th>
                            <th><i class="fas fa-briefcase"></i> Cargo</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-key"></i> Contraseña</th>
                            <th><i class="fas fa-cogs"></i> Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['Id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($row['Cargo']); ?></td>
                                <td><?php echo htmlspecialchars($row['Email']); ?></td>
                                <td class="password-field">
                                    <?php echo htmlspecialchars($row['Contrasena']); ?>
                                </td>
                                <td>
                                    <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['Id']; ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $row['Id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['Id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['Id']; ?>">
                                                <i class="fas fa-edit"></i> Editar Registro #<?php echo $row['Id']; ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="id" value="<?php echo $row['Id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="nombre<?php echo $row['Id']; ?>" class="form-label">
                                                        <i class="fas fa-user"></i> Nombre Completo
                                                    </label>
                                                    <input type="text" class="form-control" id="nombre<?php echo $row['Id']; ?>" name="nombre" value="<?php echo htmlspecialchars($row['Nombre']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="cargo<?php echo $row['Id']; ?>" class="form-label">
                                                        <i class="fas fa-briefcase"></i> Cargo
                                                    </label>
                                                    <input type="text" class="form-control" id="cargo<?php echo $row['Id']; ?>" name="cargo" value="<?php echo htmlspecialchars($row['Cargo']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="email<?php echo $row['Id']; ?>" class="form-label">
                                                        <i class="fas fa-envelope"></i> Correo Electrónico
                                                    </label>
                                                    <input type="email" class="form-control" id="email<?php echo $row['Id']; ?>" name="email" value="<?php echo htmlspecialchars($row['Email']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="contrasena<?php echo $row['Id']; ?>" class="form-label">
                                                        <i class="fas fa-key"></i> Contraseña
                                                    </label>
                                                    <input type="password" class="form-control" id="contrasena<?php echo $row['Id']; ?>" name="contrasena" value="<?php echo htmlspecialchars($row['Contrasena']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                                <button type="submit" name="update" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Guardar Cambios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>