document.addEventListener('DOMContentLoaded', function() {
    const markInputs = document.querySelectorAll('.mark-input');

    markInputs.forEach((input, index) => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                
                // Save the value to local storage
                const candidateId = input.dataset.candidateId;
                localStorage.setItem('mark_' + candidateId, input.value);

                // Move to the next input field
                if (markInputs[index + 1]) {
                    markInputs[index + 1].focus();
                }
            }
        });

        // Populate the input from local storage if available
        const candidateId = input.dataset.candidateId;
        const storedValue = localStorage.getItem('mark_' + candidateId);
        if (storedValue !== null) {
            input.value = storedValue;
        }
    });
});

// Sorting function for table
function sortTable(n) {
    const table = document.getElementById("marksTable");
    const rows = table.rows;
    let switching = true;
    let dir = "asc"; 
    let switchcount = 0;

    while (switching) {
        switching = false;
        const rowsArray = Array.from(rows).slice(1);

        for (let i = 0; i < rowsArray.length - 1; i++) {
            let shouldSwitch = false;
            const x = rowsArray[i].getElementsByTagName("TD")[n];
            const y = rowsArray[i + 1].getElementsByTagName("TD")[n];

            if (dir === "asc") {
                if (n === 2 ? parseInt(x.innerHTML) > parseInt(y.innerHTML) : x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            } else if (dir === "desc") {
                if (n === 2 ? parseInt(x.innerHTML) < parseInt(y.innerHTML) : x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            }
        }
        if (shouldSwitch) {
            rowsArray[i].parentNode.insertBefore(rowsArray[i + 1], rowsArray[i]);
            switching = true;
            switchcount++; 
        } else {
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
}

$(document).ready(function() {
    // Autocomplete search for candidate names and index numbers
    $("#search_candidate").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "search_candidates.php",
                dataType: "json",
                data: {
                    term: request.term,
                    school_id: <?= isset($_GET['school_id']) ? intval($_GET['school_id']) : 0; ?>
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            // Filter the table based on the selected candidate
            const candidateName = ui.item.label.toLowerCase();
            $("#candidate_table_body tr").each(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(candidateName) > -1);
            });
        }
    });
});