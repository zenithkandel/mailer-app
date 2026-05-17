<?php

require_once __DIR__ . '/../config.php';

requireLogin();

$config = getUserConfig();
?>

<div class="page-header">
    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-gear"></i> Settings</h2>
</div>

<div id="settingsAlert"></div>

<div class="compose-form">
    <div class="form-group">
        <label for="senderName"><i class="fa-sharp-duotone fa-thin fa-user"></i> Display Name</label>
        <input type="text" id="senderName" placeholder="Your name as recipients see it" value="<?php echo htmlspecialchars($config['senderName'] ?? ''); ?>">
        <small style="color: var(--text-muted); display: block; margin-top: 4px;">This name will appear in the "From" field for recipients</small>
    </div>
    
    <div class="form-group">
        <label><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signatures</label>
        <div id="signatureList">
            <?php
            $signatures = $config['signatures'] ?? [];
            if (empty($signatures)):
            ?>
                <p style="color: var(--text-muted); font-size: 13px;">No signatures yet. Add one below.</p>
            <?php else: 
                $i = 0;
                foreach ($signatures as $sig):
            ?>
                    <div class="signature-item" style="margin-bottom: 16px; padding: 12px; background: var(--bg-main); border: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong><?php echo htmlspecialchars($sig['name']); ?></strong>
                            <button type="button" class="btn-icon" onclick="deleteSignature(<?php echo $i; ?>)" title="Delete">
                                <i class="fa-sharp-duotone fa-thin fa-trash"></i>
                            </button>
                        </div>
                        <div style="font-size: 13px; color: var(--text-secondary); white-space: pre-wrap;"><?php echo htmlspecialchars($sig['content']); ?></div>
                    </div>
            <?php 
                $i++;
                endforeach; 
            endif;
            ?>
        </div>
    </div>
    
    <div class="form-group" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
        <label>Add New Signature</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <input type="text" id="sigName" placeholder="Signature name (e.g. Work, Personal)">
            <input type="text" id="sigShortcut" placeholder="Shortcut (e.g. /w, /p)">
        </div>
        <textarea id="sigContent" placeholder="Signature content..." style="margin-top: 12px; min-height: 100px;"></textarea>
        <button type="button" class="btn btn-secondary" onclick="addSignature()" style="margin-top: 12px;">
            <i class="fa-sharp-duotone fa-thin fa-plus"></i> Add Signature
        </button>
    </div>
    
    <div class="compose-actions">
        <button type="button" class="btn btn-primary" onclick="saveSettings()">
            <i class="fa-sharp-duotone fa-thin fa-save"></i> Save Settings
        </button>
    </div>
</div>

<script>
async function saveSettings() {
    var senderName = document.getElementById('senderName').value;
    var alertDiv = document.getElementById('settingsAlert');
    
    try {
        var formData = new FormData();
        formData.append('senderName', senderName);
        
        var response = await fetch('../api.php?action=saveSettings', {
            method: 'POST',
            body: formData
        });
        var result = await response.json();
        
        if (result.success) {
            alertDiv.innerHTML = '<div class="alert alert-success"><i class="fa-sharp-duotone fa-thin fa-circle-check"></i> Settings saved!</div>';
            setTimeout(function() { alertDiv.innerHTML = ''; }, 3000);
        } else {
            alertDiv.innerHTML = '<div class="alert alert-error"><i class="fa-sharp-duotone fa-thin fa-circle-exclamation"></i> ' + (result.error || 'Failed to save') + '</div>';
        }
    } catch (err) {
        alertDiv.innerHTML = '<div class="alert alert-error">Error saving settings</div>';
    }
}

async function addSignature() {
    var name = document.getElementById('sigName').value;
    var shortcut = document.getElementById('sigShortcut').value;
    var content = document.getElementById('sigContent').value;
    
    if (!name || !content) {
        alert('Please provide name and content');
        return;
    }
    
    try {
        var formData = new FormData();
        formData.append('name', name);
        formData.append('shortcut', shortcut);
        formData.append('content', content);
        
        var response = await fetch('../api.php?action=addSignature', {
            method: 'POST',
            body: formData
        });
        var result = await response.json();
        
        if (result.success) {
            document.getElementById('content-frame').src = 'pages/settings-content.php';
        } else {
            alert(result.error || 'Error adding signature');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function deleteSignature(index) {
    if (!confirm('Delete this signature?')) return;
    
    try {
        var response = await fetch('../api.php?action=deleteSignature&index=' + index);
        var result = await response.json();
        
        if (result.success) {
            document.getElementById('content-frame').src = 'pages/settings-content.php';
        } else {
            alert(result.error || 'Error deleting signature');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>