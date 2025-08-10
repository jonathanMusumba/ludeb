<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Examination System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('/ludeb/assets/background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
        }
        .container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 30px;
            border-radius: 15px;
            margin-top: 50px;
            max-width: 600px;
        }
        .form-group label {
            color: #fff;
            font-weight: bold;
        }
        h1, h2 {
            color: #ffd700;
            text-align: center;
        }
        .btn-primary {
            background-color: #ffd700;
            border: none;
            color: #000;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #ffc107;
        }
        .btn-secondary {
            width: 100%;
            margin-bottom: 15px;
        }
        .form-control, .form-control-file {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 5px;
        }
        .error {
            color: #ff4d4d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Setup Examination System</h1>
        <form id="setupForm" action="/ludeb/php/setup.php" method="post" enctype="multipart/form-data">
            <h2>Board Settings</h2>
            <div class="form-group">
                <label for="boardName">Examination Board Name:</label>
                <input type="text" class="form-control" id="boardName" name="boardName" required>
            </div>
            <div class="form-group">
                <label for="examYear">Examination Year:</label>
                <input type="number" class="form-control" id="examYear" name="examYear" min="2000" max="2099" required>
            </div>
            <div class="form-group">
                <label for="contactEmail">Contact Email:</label>
                <input type="email" class="form-control" id="contactEmail" name="contactEmail" required>
            </div>
            <div class="form-group">
                <label for="logo">Upload Logo:</label>
                <input type="file" class="form-control-file" id="logo" name="logo" accept="image/*">
            </div>
            <h2>System Administrator</h2>
            <div class="form-group">
                <label for="adminUsername">Admin Username:</label>
                <input type="text" class="form-control" id="adminUsername" name="adminUsername" required>
            </div>
            <div class="form-group">
                <label for="adminEmail">Admin Email:</label>
                <input type="email" class="form-control" id="adminEmail" name="adminEmail" required>
            </div>
            <div class="form-group">
                <label for="adminPassword">Admin Password:</label>
                <input type="password" class="form-control" id="adminPassword" name="adminPassword" required>
            </div>
            <h2>Additional Users</h2>
            <div id="userManagement">
                <div class="user-entry">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" name="username[]" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" class="form-control" name="email[]" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" name="password[]" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select class="form-control" name="role[]">
                            <option value="System Admin">System Admin</option>
                            <option value="Examination Administrator">Examination Administrator</option>
                            <option value="Data Entrant">Data Entrant</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary" id="addUserButton">Add Another User</button>
            <button type="submit" class="btn btn-primary mt-3">Save Settings</button>
        </form>
    </div>

    <script>
        document.getElementById('addUserButton').addEventListener('click', function () {
            const userManagementDiv = document.getElementById('userManagement');
            const userDiv = document.createElement('div');
            userDiv.className = 'user-entry';
            userDiv.innerHTML = `
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" name="username[]" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" name="email[]" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" name="password[]" required>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select class="form-control" name="role[]">
                        <option value="System Admin">System Admin</option>
                        <option value="Examination Administrator">Examination Administrator</option>
                        <option value="Data Entrant">Data Entrant</option>
                    </select>
                </div>
            `;
            userManagementDiv.appendChild(userDiv);
        });

        document.getElementById('setupForm').addEventListener('submit', function (e) {
            const examYear = document.getElementById('examYear').value;
            if (examYear < 2000 || examYear > 2099) {
                e.preventDefault();
                alert('Examination Year must be between 2000 and 2099.');
            }
        });
    </script>
</body>
</html>