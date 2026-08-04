<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Advanced Search</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php
require "_pe_starter.php";

$q='';
if (isset($_REQUEST['q'])) $q=$_REQUEST['q'];
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">Advanced Search</h1>
        </div>
        <div class="breadcrumb"><a href="home.php">Home</a> / Advanced Search</div>

        <div class="adv-search-options">
            <input type="text" id="advSearchInput" class="filter-input" placeholder="Search text"
                value="<?php echo htmlspecialchars($q); ?>" style="max-width:500px; margin-bottom:15px;">

            <div class="adv-search-checkboxes">
                <label><input type="checkbox" id="chkReport" checked> Reports</label>
                <label><input type="checkbox" id="chkStorage" checked> Storage</label>
                <label><input type="checkbox" id="chkWorkflow" checked> Workflows</label>
                <label><input type="checkbox" id="chkGlossary" checked> Glossary</label>
                <label><input type="checkbox" id="chkTasks" checked> Tasks</label>
            </div>
            <div class="adv-search-checkboxes">
                <label><input type="checkbox" id="chkName" checked> Search in Name</label>
                <label><input type="checkbox" id="chkShortDescription" checked> Search in Description</label>
                <label id="chkColumnsWrapper"><input type="checkbox" id="chkColumns"> Also search Table Columns</label>
            </div>
            <div class="adv-search-checkboxes">
                <label><input type="checkbox" id="chkSortAlpha"> Sort alphabetically by Name</label>
            </div>
        </div>

        <div id="advSearchResults" class="adv-search-results"></div>
    </div>
</div>
<script>
(function() {
    const input = document.getElementById('advSearchInput');
    const resultsBox = document.getElementById('advSearchResults');
    const chkReport = document.getElementById('chkReport');
    const chkStorage = document.getElementById('chkStorage');
    const chkWorkflow = document.getElementById('chkWorkflow');
    const chkGlossary = document.getElementById('chkGlossary');
    const chkTasks = document.getElementById('chkTasks');
    const chkName = document.getElementById('chkName');
    const chkShortDescription = document.getElementById('chkShortDescription');
    const chkColumns = document.getElementById('chkColumns');
    const chkColumnsWrapper = document.getElementById('chkColumnsWrapper');
    const chkSortAlpha = document.getElementById('chkSortAlpha');

    let debounceTimer=null;
    let currentRequest=0;
    let lastItems=[];

    function runSearch() {
        clearTimeout(debounceTimer);
        debounceTimer=setTimeout(async function() {
            const q=input.value.trim();
            if (q.length<2) { resultsBox.innerHTML=''; return; }
            const reqId=++currentRequest;
            const params=new URLSearchParams({
                q: q,
                report: chkReport.checked ? 1 : 0,
                storage: chkStorage.checked ? 1 : 0,
                workflow: chkWorkflow.checked ? 1 : 0,
                glossary: chkGlossary.checked ? 1 : 0,
                tasks: chkTasks.checked ? 1 : 0,
                name: chkName.checked ? 1 : 0,
                shortDescription: chkShortDescription.checked ? 1 : 0,
                columns: chkColumns.checked ? 1 : 0
            });
            try {
                const r=await fetch('api_AdvSearch.php?'+params.toString());
                const items=await r.json();
                if (reqId!==currentRequest) return;
                lastItems=items;
                renderResults(items);
            } catch (err) {
                console.error('Advanced search failed:', err);
            }
        }, 250);
    }

    function renderResults(items) {
        resultsBox.innerHTML='';

        if (items.error || !items.length) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'search-result-empty';
            emptyDiv.textContent = items.error || 'No matching item';
            resultsBox.appendChild(emptyDiv);
            return;
        }

        if (chkSortAlpha.checked) {
            items = items.slice().sort(function(a,b) {
                return a.name.localeCompare(b.name);
            });
        }

        items.forEach(function(it) {
            const itemLink = document.createElement('a');
            itemLink.className = 'search-result-item';
            itemLink.href = it.url;

            const icon = document.createElement('img');
            icon.className = 'search-result-icon';
            icon.src = it.icon;

            const textDiv = document.createElement('div');
            textDiv.className = 'search-result-text';

            const nameDiv = document.createElement('div');
            nameDiv.className = 'search-result-name';
            nameDiv.innerHTML = it.name;

            const descDiv = document.createElement('div');
            descDiv.className = 'search-result-desc';
            descDiv.innerHTML = it.shortDescription || '';

            textDiv.appendChild(nameDiv);
            textDiv.appendChild(descDiv);
            itemLink.appendChild(icon);
            itemLink.appendChild(textDiv);
            resultsBox.appendChild(itemLink);
        });
    }

    chkStorage.addEventListener('change', function() { 
            chkColumnsWrapper.style.display = chkStorage.checked ? '' : 'none';
            runSearch(); 
        });
    chkReport.addEventListener('change', runSearch);
    chkWorkflow.addEventListener('change', runSearch);
    chkGlossary.addEventListener('change', runSearch);
    chkTasks.addEventListener('change', runSearch);
    chkName.addEventListener('change', runSearch);
    chkShortDescription.addEventListener('change', runSearch);
    chkColumns.addEventListener('change', runSearch);
    input.addEventListener('input', runSearch);

    chkSortAlpha.addEventListener('change', function() { renderResults(lastItems); });

    if (input.value.trim()!=='') runSearch();

    const headerSearchInput = document.getElementById('assetSearchInput');
    if (headerSearchInput) headerSearchInput.style.display = 'none';
})();
</script>
<?php $idAsset=0; ?>
</body>
