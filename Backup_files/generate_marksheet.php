<?php
// Initialize arrays for summaries
$summary = [
    'male' => ['div1' => 0, 'div2' => 0, 'div3' => 0, 'div4' => 0, 'divu' => 0],
    'female' => ['div1' => 0, 'div2' => 0, 'div3' => 0, 'div4' => 0, 'divu' => 0],
    'total' => ['div1' => 0, 'div2' => 0, 'div3' => 0, 'div4' => 0, 'divu' => 0]
];

// Display table header
echo "<table border='1'>
      <tr>
        <th>INDEX NUMBER</th>
        <th>CANDIDATE NAME</th>
        <th>SEX</th>
        <th>ENGLISH MARK</th>
        <th>ENGLISH GRADE</th>
        <th>MATHEMATICS MARK</th>
        <th>MATHEMATICS GRADE</th>
        <th>SCIENCE MARK</th>
        <th>SCIENCE GRADE</th>
        <th>SOCIAL STUDIES MARK</th>
        <th>SOCIAL STUDIES GRADE</th>
        <th>AGGREGATES</th>
        <th>DIVISION</th>
      </tr>";

foreach ($candidates as $candidate) {
    $englishGrade = calculateGrade($candidate['english_marks']);
    $mathGrade = calculateGrade($candidate['math_marks']);
    $scienceGrade = calculateGrade($candidate['science_marks']);
    $socialGrade = calculateGrade($candidate['social_studies_marks']);

    $aggregate = $candidate['english_marks'] + $candidate['math_marks'] + $candidate['science_marks'] + $candidate['social_studies_marks'];
    $division = calculateDivision($aggregate);

    // Update summary
    $gender = strtolower($candidate['sex']);
    $summary[$gender][strtolower($division)]++;
    $summary['total'][strtolower($division)]++;

    // Display row
    echo "<tr>
            <td>{$candidate['IndexNo']}</td>
            <td>{$candidate['Candidate_Name']}</td>
            <td>{$candidate['sex']}</td>
            <td>{$candidate['english_marks']}</td>
            <td>{$englishGrade}</td>
            <td>{$candidate['math_marks']}</td>
            <td>{$mathGrade}</td>
            <td>{$candidate['science_marks']}</td>
            <td>{$scienceGrade}</td>
            <td>{$candidate['social_studies_marks']}</td>
            <td>{$socialGrade}</td>
            <td>{$aggregate}</td>
            <td>{$division}</td>
          </tr>";
}

echo "</table>";
?>

<h2>Summary</h2>
<table border='1'>
  <tr>
    <th>Division</th>
    <th>Male</th>
    <th>Female</th>
    <th>Total</th>
  </tr>
  <?php foreach ($summary['total'] as $division => $count): ?>
    <tr>
      <td><?php echo ucfirst($division); ?></td>
      <td><?php echo $summary['male'][$division]; ?></td>
      <td><?php echo $summary['female'][$division]; ?></td>
      <td><?php echo $count; ?></td>
    </tr>
  <?php endforeach; ?>
</table>
