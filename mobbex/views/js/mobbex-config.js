(function (window) {
    /**
     * Create Mobbex update button.
     */
    function createUpdateButton() {
        // Get prestashop original update button
        var btn   = document.getElementById('desc-module-update');
        var label = btn.querySelector('div');
        var icon  = btn.querySelector('i');

        // If plugin has not updates, hide button and return
        if (typeof mbbx == 'undefined' || typeof mbbx.updateVersion == 'undefined')
            return btn.style.display = 'none';

        // Set mobbex update url
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('run_update', 1);
        btn.setAttribute('href', currentUrl.href);

        // Show new version label
        label.innerHTML   = `Actualizar a ${mbbx.updateVersion}`;
        label.style.color = '#2eacce';

        // Change icon
        icon.classList      = 'material-icons';
        icon.innerHTML      = 'get_app';
        icon.style.fontSize = '32px';
    }

    /**
     * Show upgrade database button.
     */
    function showUpgradeDatabaseButton() {
        // Get prestashop original update button
        var btn   = document.querySelector('.desc-module-update');
        var label = btn.querySelector('div');

        // Show new upgrade label
        label.innerHTML   = 'Actualizar base de datos';
        label.style.color = '#2eacce';
    }

    window.addEventListener('load', function () {
        var currentUrl = new URL(window.location.href);
        var updated    = currentUrl.searchParams.get('run_update');

        if (updated) {
            // Remove update param
            currentUrl.searchParams.delete('run_update');
            window.history.pushState("", "", currentUrl.href);

            // Show success message
            showSuccessMessage('¡Modulo actualizado correctamente! Recuerde actualizar la base de datos');
            showUpgradeDatabaseButton();
        } else {
            createUpdateButton();
        }
    });
}) (window);