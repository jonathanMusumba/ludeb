<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>School Interface - Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100 font-sans">
  <!-- Navigation Bar -->
  <nav class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
      <h1 class="text-2xl font-bold">School Interface - [School Name]</h1>
      <div class="space-x-4">
        <a href="#" class="hover:underline">Dashboard</a>
        <a href="#results" class="hover:underline">Results</a>
        <a href="#resources" class="hover:underline">Resources</a>
        <a href="#announcements" class="hover:underline">Announcements</a>
        <a href="#" class="hover:underline">Logout</a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mx-auto p-6">
    <!-- Exam Year Selector -->
    <div class="mb-6">
      <label for="examYear" class="block text-lg font-semibold mb-2">Select Exam Year:</label>
      <select id="examYear" class="p-2 border rounded w-full md:w-1/3">
        <option value="2025">2025</option>
        <!-- Previous years will be populated dynamically -->
      </select>
    </div>

    <!-- Dashboard -->
    <section id="dashboard" class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold mb-4">Dashboard</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Results Download -->
        <div class="bg-blue-100 p-4 rounded-lg text-center">
          <h3 class="text-xl font-semibold">Download Results</h3>
          <p class="mt-2">Click to download your school's results for the selected year.</p>
          <button id="downloadResults" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Download</button>
        </div>
        <!-- Resources Download -->
        <div class="bg-green-100 p-4 rounded-lg text-center">
          <h3 class="text-xl font-semibold">Download Resources</h3>
          <p class="mt-2">Access learning and administrative resources.</p>
          <button id="downloadResources" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Download</button>
        </div>
        <!-- Announcements -->
        <div class="bg-yellow-100 p-4 rounded-lg text-center">
          <h3 class="text-xl font-semibold">Announcements</h3>
          <p class="mt-2" id="announcementText">No new announcements.</p>
          <button id="viewAnnouncements" class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">View All</button>
        </div>
      </div>
    </section>

    <!-- Results Section (Hidden by default) -->
    <section id="results" class="hidden bg-white p-6 mt-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold mb-4">Results</h2>
      <p>View and download detailed results for the selected exam year.</p>
      <button id="downloadDetailedResults" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Download Detailed Results</button>
    </section>

    <!-- Resources Section (Hidden by default) -->
    <section id="resources" class="hidden bg-white p-6 mt-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold mb-4">Resources</h2>
      <p>Download available resources for the selected exam year.</p>
      <button id="downloadAllResources" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Download All Resources</button>
    </section>

    <!-- Announcements Section (Hidden by default) -->
    <section id="announcements" class="hidden bg-white p-6 mt-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold mb-4">Announcements</h2>
      <ul id="announcementList" class="list-disc pl-5">
        <!-- Announcements will be populated dynamically -->
      </ul>
    </section>
  </div>

  <script>
    // Fetch school details using school_id from session
    document.addEventListener('DOMContentLoaded', () => {
      const schoolId = <?php echo isset($_SESSION['school_id']) ? $_SESSION['school_id'] : 0; ?>;
      if (schoolId) {
        axios.get(`/api/school/${schoolId}`)
          .then(response => {
            document.querySelector('nav h1').textContent = `School Interface - ${response.data.school_name}`;
          })
          .catch(error => console.error('Error fetching school details:', error));
      }

      // Populate exam years
      axios.get('/api/exam_years')
        .then(response => {
          const select = document.getElementById('examYear');
          response.data.forEach(year => {
            const option = document.createElement('option');
            option.value = year.exam_year;
            option.textContent = year.exam_year;
            select.appendChild(option);
          });
          select.value = '2025'; // Default to current year
        })
        .catch(error => console.error('Error fetching exam years:', error));

      // Navigation
      document.querySelectorAll('nav a').forEach(anchor => {
        anchor.addEventListener('click', (e) => {
          e.preventDefault();
          const target = document.querySelector(anchor.getAttribute('href'));
          document.querySelectorAll('section').forEach(section => section.classList.add('hidden'));
          target.classList.remove('hidden');
        });
      });

      // Dashboard Buttons
      document.getElementById('downloadResults').addEventListener('click', () => {
        const year = document.getElementById('examYear').value;
        window.location.href = `/api/download/results?school_id=${schoolId}&exam_year=${year}`;
      });

      document.getElementById('downloadResources').addEventListener('click', () => {
        const year = document.getElementById('examYear').value;
        window.location.href = `/api/download/resources?school_id=${schoolId}&exam_year=${year}`;
      });

      document.getElementById('viewAnnouncements').addEventListener('click', () => {
        const target = document.getElementById('announcements');
        document.querySelectorAll('section').forEach(section => section.classList.add('hidden'));
        target.classList.remove('hidden');
        axios.get(`/api/announcements?school_id=${schoolId}&exam_year=${document.getElementById('examYear').value}`)
          .then(response => {
            const list = document.getElementById('announcementList');
            list.innerHTML = '';
            response.data.forEach(ann => {
              const li = document.createElement('li');
              li.textContent = ann.details;
              list.appendChild(li);
            });
          })
          .catch(error => console.error('Error fetching announcements:', error));
      });

      // Detailed Results
      document.getElementById('downloadDetailedResults').addEventListener('click', () => {
        const year = document.getElementById('examYear').value;
        window.location.href = `/api/download/detailed_results?school_id=${schoolId}&exam_year=${year}`;
      });

      // All Resources
      document.getElementById('downloadAllResources').addEventListener('click', () => {
        const year = document.getElementById('examYear').value;
        window.location.href = `/api/download/all_resources?school_id=${schoolId}&exam_year=${year}`;
      });
    });
  </script>
</body>
</html>