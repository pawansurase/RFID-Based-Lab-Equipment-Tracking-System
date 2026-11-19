<style>
    .menu-button {
        position: fixed;
        top: 16px;
        right: 20px;
        z-index: 1001;
        border: 0;
        border-radius: 8px;
        padding: 10px 14px;
        background: #173b72;
        color: white;
        font-size: 22px;
        cursor: pointer;
    }

    .menu-panel {
        display: none;
        position: fixed;
        top: 64px;
        right: 20px;
        z-index: 1000;
        min-width: 220px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: white;
        box-shadow: 0 8px 24px #0002;
        font-family: Arial, sans-serif;
    }

    .menu-panel.open {
        display: block;
    }

    .menu-panel a {
        display: block;
        padding: 12px;
        border-radius: 6px;
        color: #172033;
        text-decoration: none;
    }

    .menu-panel a:hover {
        background: #eef4ff;
    }

    .menu-panel .delete-link {
        color: #b42318;
    }
</style>

<button class="menu-button" type="button" aria-label="Open menu" aria-expanded="false"
    onclick="toggleLabMenu()">☰</button>

<nav class="menu-panel" id="labMenu">
    <a href="live_scan.php">Live Card Scan</a>
    <a href="profile.php">Profile</a>
    <a href="students.php">Students</a>
    <a href="equipment.php">Equipment</a>
    <a href="logout.php">Log Out</a>
    <a class="delete-link" href="delete_account.php">Permanent Delete</a>
</nav>

<script>
    function toggleLabMenu() {
        const menu = document.getElementById('labMenu');
        const button = document.querySelector('.menu-button');
        const isOpen = menu.classList.toggle('open');
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
</script>