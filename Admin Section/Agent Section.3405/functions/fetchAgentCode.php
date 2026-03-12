<?php
require "../../conn.php"; // Ensure the connection file is correctly included
session_start();

if (isset($_POST['agentId'])) 
{
  $agentId = $_POST['agentId'];

  // Fetch agent code based on the selected agent ID
  $sql = mysqli_query($conn, "SELECT agentCode, agentType FROM agent WHERE agentId = '$agentId'");
  $agentCode = ''; // Default value if no agent code is found

  if (mysqli_num_rows($sql) > 0) 
  {
      $res = mysqli_fetch_assoc($sql);
      $agentCode = $res['agentCode'];
      $_SESSION['agent_agentType'] = $res['agentType'];
  }

  // Return the response as JSON
  echo json_encode([
      "agentCode" => $agentCode
  ]);
}
?>
