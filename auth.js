(function() {
    const token = localStorage.getItem('vs_token');
    const userStr = localStorage.getItem('vs_user');

    if (!token || !userStr) {
        window.location.replace('index.php');
        return;
    }

    let user;
    try {
        user = JSON.parse(userStr);
    } catch(e) {
        localStorage.clear();
        window.location.replace('index.php');
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const currentPlant = urlParams.get('plant') || '';

    if (user.role !== 'admin' && user.plant_id && currentPlant && currentPlant !== user.plant_id) {
        alert("Access Denied: You do not have permission to view this plant.");
        window.location.replace('home.php?plant=' + encodeURIComponent(user.plant_id));
        return;
    }
})();
