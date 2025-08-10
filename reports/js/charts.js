document.addEventListener('DOMContentLoaded', function() {
    // Example data for charts
    const performanceByDivisionData = {
        labels: ['Division 1', 'Division 2', 'Division 3', 'Division 4', 'Division U'],
        datasets: [{
            label: 'Number of Candidates',
            data: [120, 150, 180, 200, 50],
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#E7E9ED'],
        }]
    };

    const performanceByGenderData = {
        labels: ['Male', 'Female'],
        datasets: [{
            label: 'Number of Candidates',
            data: [300, 200],
            backgroundColor: ['#FF6384', '#36A2EB'],
        }]
    };

    const ctx1 = document.getElementById('performanceByDivision').getContext('2d');
    const ctx2 = document.getElementById('performanceByGender').getContext('2d');

    new Chart(ctx1, {
        type: 'pie',
        data: performanceByDivisionData,
        options: {
            responsive: true
        }
    });

    new Chart(ctx2, {
        type: 'bar',
        data: performanceByGenderData,
        options: {
            responsive: true
        }
    });
});
