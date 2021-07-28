(function (window) {
    function createUpdateButton() {
        // Get prestashop update button
        var btn   = document.getElementById('desc-module-update');
        var label = btn.querySelector('div');
        var icon  = btn.querySelector('i');

        // If plugin has not updates, hide update button and return
        if (typeof mbbx == 'undefined' || typeof mbbx.updateVersion == 'undefined')
            return btn.style.display = 'none';

        // Change update endpoint url
        btn.setAttribute('href', mbbx.updateUrl);

        // Add some styles
        label.innerHTML     = `Actualizar a ${mbbx.updateVersion}`;
        label.style.color   = '#2eacce';
        icon.classList      = 'material-icons';
        icon.innerHTML      = 'get_app';
        icon.style.fontSize = '32px';
    }

    window.addEventListener('load', function () {
        createUpdateButton();
    });
}) (window);