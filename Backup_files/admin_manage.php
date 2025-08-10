<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .pagination { margin: 20px 0; }
        .pagination a { margin: 0 5px; text-decoration: none; color: #007BFF; }
        .pagination a.active { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Schools List</h1>
    <div id="schools-container">
        <!-- Schools data will be loaded here -->
    </div>
    <div class="pagination" id="pagination-container">
        <!-- Pagination links will be loaded here -->
    </div>

    <script>
        function fetchSchools(page = 1) {
            fetch(`manage.php?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    const schoolsContainer = document.getElementById('schools-container');
                    const paginationContainer = document.getElementById('pagination-container');

                    // Clear previous content
                    schoolsContainer.innerHTML = '';
                    paginationContainer.innerHTML = '';

                    // Create schools table
                    const table = document.createElement('table');
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>School Name</th>
                                <th>Subjects</th>
                                <th>Total Candidates</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    `;
                    const tbody = table.querySelector('tbody');
                    
                    data.schools.forEach(school => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${school.name}</td>
                            <td>
                                <ul>
                                    ${school.subjects.map(subject => `<li>${subject.code}: ${subject.count}</li>`).join('')}
                                </ul>
                            </td>
                            <td>${school.total_candidates}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    schoolsContainer.appendChild(table);

                    // Create pagination links
                    const { page, total_pages } = data;
                    if (total_pages > 1) {
                        for (let i = 1; i <= total_pages; i++) {
                            const link = document.createElement('a');
                            link.href = '#';
                            link.textContent = i;
                            if (i === page) {
                                link.classList.add('active');
                            }
                            link.addEventListener('click', (e) => {
                                e.preventDefault();
                                fetchSchools(i);
                            });
                            paginationContainer.appendChild(link);
                        }
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        // Initial load
        fetchSchools();
    </script>
</body>
</html>
