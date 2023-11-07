<!DOCTYPE html>
<html>
<head>
  <!-- Include MaterializeCSS and jQuery libraries -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</head>
<body>
  <div class="container">
    <h3>MySQL Database Schema Fixer</h3>
    <div class="row">
      <!-- Source Database Credentials -->
      <div class="col s6">
        <div class="card">
          <div class="card-content">
            <span class="card-title">Source Database Credentials</span>
            <div class="input-field">
              <input type="text" id="source-host" placeholder="Host">
            </div>
            <div class="input-field">
              <input type="text" id="source-username" placeholder="Username">
            </div>
            <div class="input-field">
              <input type="password" id="source-password" placeholder="Password">
            </div>
            <div class="input-field">
              <input type="text" id="source-database" placeholder="Database Name">
            </div>
          </div>
        </div>
      </div>

      <!-- Target Database Credentials -->
      <div class="col s6">
        <div class="card">
          <div class="card-content">
            <span class="card-title">Target Database Credentials</span>
            <div class="input-field">
              <input type="text" id="target-host" placeholder="Host">
            </div>
            <div class="input-field">
              <input type="text" id="target-username" placeholder="Username">
            </div>
            <div class="input-field">
              <input type="password" id="target-password" placeholder="Password">
            </div>
            <div class="input-field">
              <input type="text" id="target-database" placeholder="Database Name">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Compare Button -->
    <div class="row">
      <div class="col s12">
        <p>
          <label>
            <input type="checkbox" id="remember-me" />
            <span>Remember Me</span>
          </label>
        </p>
        <div style='display: flex;'>
          <button style='margin-right:10px' class="btn waves-effect waves-light right" id="swap-button">Swap Values</button>
          <button class="btn waves-effect waves-light" id="compare-button">Compare</button>
        </div>
         <br>
        <div class="progress compare_loader" style="display:none">
            <div class="indeterminate"></div>
        </div>
      </div>
    </div>

    <div class="row" id="comparison-result" style="display:none">
      <div class="col s12">
        <div class="card">
          <div class="card-content">
            <span class="card-title">Comparison Result  </span>
            <div >
                <ul>
                  <li><a href="" id='mistach_tables_sql' download='mismatch_tables.sql'>mismatch_tables.sql</a></li>
                  <li><a href="" id='mistach_entities_sql' download='mistach_entities.sql'>mistach_entities.sql</a></li>
                  <li><a href="" id='complete_schema_export_file' download='complete_schema_export_file.json'>complete_schema_export_file.json</a></li>
                  <li><a href="" id='mistach_entities_export_file' download='mistach_entities_export_file.json'>mistach_entities_export_file.json</a></li>
                </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    $(document).ready(function(){

      // Function to save input field values to local storage when the "Remember Me" checkbox is checked
      $('#remember-me').change(function() {
        if ($(this).is(':checked')) {
          $('input[type="text"], input[type="password"]').each(function() {
            localStorage.setItem($(this).attr('id'), $(this).val());
          });
        } else {
          localStorage.clear();
        }
      });
      // Function to swap values between source and target databases
      $('#swap-button').click(function() {
        var sourceHost = $("#source-host").val();
        var sourceUsername = $("#source-username").val();
        var sourcePassword = $("#source-password").val();
        var sourceDatabase = $("#source-database").val();

        var targetHost = $("#target-host").val();
        var targetUsername = $("#target-username").val();
        var targetPassword = $("#target-password").val();
        var targetDatabase = $("#target-database").val();

        $("#target-host").val(sourceHost);
        $("#target-username").val(sourceUsername);
        $("#target-password").val(sourcePassword);
        $("#target-database").val(sourceDatabase);
        $("#source-host").val(targetHost);
        $("#source-username").val(targetUsername);
        $("#source-password").val(targetPassword);
        $("#source-database").val(targetDatabase);

      });
      // Function to load input field values from local storage when the page loads
      function loadInputValues() {
        $('input[type="text"], input[type="password"]').each(function() {
          const storedValue = localStorage.getItem($(this).attr('id'));
          if (storedValue !== null) {
            $(this).val(storedValue);
          }
        });
      }

      // Call the loadInputValues function when the page loads
      loadInputValues();

      $('#compare-button').click(function() {
        if ($('#remember-me').is(':checked')) {
          $('input[type="text"], input[type="password"]').each(function() {
            localStorage.setItem($(this).attr('id'), $(this).val());
          });
        }
      });
      // Add a click event handler for the "Compare" button
      $("#compare-button").click(function(){
        $('#comparison-result').hide();
        // Retrieve values from input fields
        var sourceHost = $("#source-host").val();
        var sourceUsername = $("#source-username").val();
        var sourcePassword = $("#source-password").val();
        var sourceDatabase = $("#source-database").val();

        if(sourceHost=='' || sourceUsername=='' || sourcePassword=='' || sourceDatabase==''){
            alert("Please fill all the fields For source database credentials");
            return false;
        }
        var targetHost = $("#target-host").val();
        var targetUsername = $("#target-username").val();
        var targetPassword = $("#target-password").val();
        var targetDatabase = $("#target-database").val();

        if(targetHost=='' || targetUsername=='' || targetPassword=='' || targetDatabase==''){
            alert("Please fill all the fields For target database credentials");
            return false;
        }

        $(".compare_loader").show();
        $("#compare-button").hide();

        
        // Make an AJAX call here to compare the databases
        // Replace this with your actual AJAX logic
        $.ajax({
          url: "action.php",
          method: "POST",
          data: {
            sourceHost: sourceHost,
            sourceUsername: sourceUsername,
            sourcePassword: sourcePassword,
            sourceDatabase: sourceDatabase,
            targetHost: targetHost,
            targetUsername: targetUsername,
            targetPassword: targetPassword,
            targetDatabase: targetDatabase
          },
          success: function(response) {
            $(".compare_loader").hide();
            $("#compare-button").show();
            let responseArray = JSON.parse(response);
            console.log(responseArray);

            if(responseArray.success== false ){
                alert(responseArray.message);
            }
            else{
                alert(responseArray.message);
                $('#comparison-result').show();
                $("#mistach_tables_sql").attr("href", responseArray.output.mistach_tables_sql);
                $("#mistach_entities_sql").attr("href", responseArray.output.mistach_entities_sql);
                $("#complete_schema_export_file").attr("href", responseArray.output.complete_schema_export_file);
                $("#mistach_entities_export_file").attr("href", responseArray.output.mistach_entities_export_file);
            }
            // Handle the response from the server
          },
          error: function(xhr, status, error) {
            $(".compare_loader").hide();
            $("#compare-button").show();
            
            // Handle errors
          }
        });
      });
    });
  </script>
</body>
</html>
