<?php
#**************************************************************************
#  openSIS is a free student information system for public and non-public 
#  schools from Open Solutions for Education, Inc. web: www.os4ed.com
#
#  openSIS is  web-based, open source, and comes packed with features that 
#  include student demographic info, scheduling, grade book, attendance, 
#  report cards, eligibility, transcripts, parent portal, 
#  student portal and more.   
#
#  Visit the openSIS web site at http://www.opensis.com to learn more.
#  If you have question regarding this system or the license, please send 
#  an email to info@os4ed.com.
#
#  This program is released under the terms of the GNU General Public License as  
#  published by the Free Software Foundation, version 2 of the License. 
#  See license.txt.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#***************************************************************************************
error_reporting(0);
session_start();
echo '<script type="text/javascript">
var page=parent.location.href.replace(/.*\//,"");
if(page && page!="index.php"){
	window.location.href="index.php";
	}
</script>';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>openSIS Installer</title>
        <link href="../assets/css/icons/fontawesome/styles.min.css" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="assets/sweetalert2/css/sweetalert2.css">
        <link rel="stylesheet" href="assets/css/installer.css?v=<?php echo rand(000, 999); ?>" type="text/css" />
        <noscript><META http-equiv=REFRESH content='0;url=../EnableJavascript.php' /></noscript>
        <script src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/Validator.js"></script>
        <script src="assets/sweetalert2/js/sweetalert2.min.js"></script>
        <style>
            #progress-container {
                display: none;
                margin: 30px 0;
                padding: 25px;
                background: #f8f9fa;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            #progress-container h4 {
                color: #2c3e50;
                margin-bottom: 20px;
                text-align: center;
            }
            .progress {
                width: 100%;
                height: 45px;
                background: #e9ecef;
                border-radius: 25px;
                overflow: hidden;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.5s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 18px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            #status-messages {
                max-height: 350px;
                overflow-y: auto;
                background: white;
                padding: 15px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
            }
            #status-messages p {
                margin: 10px 0;
                padding: 8px 12px;
                border-left: 4px solid #007bff;
                background: #f8f9fa;
                border-radius: 4px;
            }
            #status-messages p.error {
                color: #dc3545;
                border-left-color: #dc3545;
                background: #f8d7da;
                font-weight: bold;
            }
            #status-messages p.success {
                color: #28a745;
                border-left-color: #28a745;
                background: #d4edda;
                font-weight: bold;
            }
            #status-messages p.info {
                color: #17a2b8;
                border-left-color: #17a2b8;
            }
        </style>
    </head>
    <body class="outer-body">
        <section class="login">
            <div class="login-wrapper">
                <div class="panel">
                    <div class="panel-heading">
                        <div class="logo">
                            <img src="assets/images/opensis_logo.png" alt="openSIS">
                        </div>
                        <h3>openSIS Installation - Database Selection</h3>
                    </div>
                    <div class="panel-body">
                        <div class="installation-steps-wrapper">
                            <div class="installation-instructions">
                                <ul class="installation-steps-label">
                                    <li>Choose Package</li>
                                    <li>System Requirements</li>
                                    <li>Database Connection</li>
                                    <li class="active">Database Selection</li>
                                    <li>School Information</li>
                                    <li>Site Admin Account Setup</li>
                                    <li>Ready to Go!</li>
                                </ul>
                            </div>
                            <div class="installation-steps">
                                <h4 class="m-t-0 m-b-5">System needs a new database</h4>
                                <p class="m-b-20 text-muted">(This process is optimized to prevent timeouts)</p>
                                <div id="error" class="m-b-5"></div>
                                
                                <!-- Progress Container (hidden initially) -->
                                <div id="progress-container">
                                    <h4><i class="fa fa-database"></i> Installing Database...</h4>
                                    <div class="progress">
                                        <div id="progress-bar" class="progress-bar" style="width: 0%">
                                            <span id="progress-text">0%</span>
                                        </div>
                                    </div>
                                    <div id="status-messages"></div>
                                </div>
                                
                                <?php if (isset($_REQUEST['err'])) { ?>
                                    <script type='text/javascript'>
                                        swal({
                                            title: 'Oops!',
                                            text: '<?php echo $_REQUEST['err']; ?>',
                                            type: 'error',
                                            confirmButtonText: 'Close'
                                        }).then(function (){
                                                history.back();
                                            });
                                    </script>
                                <?php } ?>
                                
                                <form name='step2' id='step2' method='post'>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="control-label">Database Name</label>
                                                <input type="text" name="db" id="db" size="20" value="opensis" class="form-control"  />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="control-label">&nbsp;</label>
                                                <div class="m-t-0">
                                                    <label class="radio-inline"><input type="radio" name="data_choice" value="purgedb" /> Remove data from existing database</label>
                                                    <br>
                                                    <label class="control-label m-0">OR</label>
                                                    <br>
                                                    <label class="radio-inline"><input type="radio" name="data_choice" value="newdb" checked /> Create new database</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr/>
                                    <div class="text-right">
                                        <button type="button" id="submit-btn" onclick="startDatabaseCreation()" class="btn btn-success">
                                            <i class="fa fa-play"></i> Save & Next
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <footer>
                    Copyright &copy; Open Solutions for Education, Inc. (<a href="http://www.os4ed.com">OS4ED</a>).
                </footer>
            </div>
        </section>
        
        <script type="text/javascript">
            function startDatabaseCreation() {
                var db_name = document.getElementById('db').value;
                var data_choice = document.querySelector('input[name="data_choice"]:checked');
                
                if (db_name.trim() === '') {
                    swal('Error', 'Please enter a database name', 'error');
                    return;
                }
                
                if (!data_choice) {
                    swal('Error', 'Please select an option', 'error');
                    return;
                }
                
                // Guardar en sesión via AJAX
                fetch('Createdb-ajax.php?action=save_session', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'db=' + encodeURIComponent(db_name) + '&data_choice=' + data_choice.value
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Ocultar formulario y mostrar progreso
                        document.getElementById('step2').style.display = 'none';
                        document.getElementById('progress-container').style.display = 'block';
                        document.getElementById('submit-btn').disabled = true;
                        
                        addStatus('🚀 Starting database installation...', 'info');
                        
                        // Iniciar proceso
                        createOrPurgeDatabase(db_name, data_choice.value);
                    } else {
                        swal('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    swal('Error', 'Failed to start installation: ' + error.message, 'error');
                });
            }
            
            function createOrPurgeDatabase(dbName, choice) {
                addStatus('📊 Checking database: ' + dbName, 'info');
                
                fetch('Createdb-ajax.php?action=check_and_prepare&db=' + encodeURIComponent(dbName) + '&choice=' + choice)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        
                        updateProgress(10, data.message);
                        addStatus('✓ ' + data.message, 'success');
                        
                        // Continuar con schema
                        return executeSchema(0);
                    })
                    .catch(error => {
                        addStatus('❌ Error: ' + error.message, 'error');
                        document.getElementById('submit-btn').disabled = false;
                        document.getElementById('step2').style.display = 'block';
                    });
            }
            
            function executeSchema(batch) {
                addStatus('⚙️ Creating database schema (batch ' + (batch + 1) + ')...', 'info');
                
                return fetch('Createdb-ajax.php?action=execute_schema&batch=' + batch)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        
                        updateProgress(10 + (data.progress * 0.4), data.message);
                        
                        if (!data.complete) {
                            return executeSchema(batch + 1);
                        } else {
                            addStatus('✓ Schema created successfully', 'success');
                            return executeProcs(0);
                        }
                    });
            }
            
            function executeProcs(batch) {
                addStatus('⚙️ Creating stored procedures (batch ' + (batch + 1) + ')...', 'info');
                
                return fetch('Createdb-ajax.php?action=execute_procs&batch=' + batch)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        
                        updateProgress(50 + (data.progress * 0.35), data.message);
                        
                        if (!data.complete) {
                            return executeProcs(batch + 1);
                        } else {
                            addStatus('✓ Procedures created successfully', 'success');
                            return createTriggers();
                        }
                    });
            }
            
            function createTriggers() {
                addStatus('⚙️ Creating database triggers...', 'info');
                
                return fetch('Createdb-ajax.php?action=create_triggers')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        
                        updateProgress(95, data.message);
                        addStatus('✓ Triggers created successfully', 'success');
                        return finalize();
                    });
            }
            
            function finalize() {
                addStatus('🎉 Finalizing installation...', 'info');
                
                return fetch('Createdb-ajax.php?action=finalize')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        
                        updateProgress(100, 'Installation Complete!');
                        addStatus('✅ ' + data.message, 'success');
                        
                        setTimeout(() => {
                            window.location.href = 'Step3.php';
                        }, 2000);
                    });
            }
            
            function updateProgress(percent, message) {
                percent = Math.min(100, Math.max(0, percent));
                document.getElementById('progress-bar').style.width = percent + '%';
                document.getElementById('progress-text').textContent = Math.round(percent) + '%';
            }
            
            function addStatus(message, type = 'info') {
                const statusDiv = document.getElementById('status-messages');
                const p = document.createElement('p');
                p.textContent = message;
                p.className = type;
                statusDiv.appendChild(p);
                statusDiv.scrollTop = statusDiv.scrollHeight;
            }
        </script>
    </body>
</html>
