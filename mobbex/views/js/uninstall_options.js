/**
 * Add Mobbex Uninstall options to modal
 */
 function addMbbxOptions() {
    // Get Mobbex Uninstall modal from page
    var uninstallModal = document.querySelector('#module-modal-confirm-mobbex-uninstall .modal-content .modal-body');

    // Create elements
    var container = document.createElement('div');
    var label     = document.createElement('label');
    var input     = document.createElement('input');

    // Set attributes
    container.classList.add('col-md-12');
    input.setAttribute("id", "mbbxRemoveConfig");
    input.setAttribute("type", "checkbox");

    // Add content to page
    var content = ' Opcional: Eliminar toda la configuración del módulo.';
    label.appendChild(input);
    label.appendChild(document.createTextNode(content));
    uninstallModal.appendChild(container).appendChild(label);
}

/**
 * Send Mobbex options data to back-end on click
 */
function sendMbbxData() {
    var option = document.querySelector('#mbbxRemoveConfig');

    option.onclick = function () {
        if (option.checked) {
            // Send data as Cookie
            if (document.cookie.indexOf('mbbx_remove_config') === -1) {
                document.cookie = 'mbbx_remove_config=true';
            }
        } else {
            // Remove Cookie
            if (document.cookie.indexOf('mbbx_remove_config') != -1) {
                document.cookie = 'mbbx_remove_config=; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
            }
        }
    }
}

// First remove Mobbex cookies previously added
document.cookie = 'mbbx_remove_config=; expires=Thu, 01 Jan 1970 00:00:00 UTC;';

window.addEventListener('load', function () {
    addMbbxOptions();
    sendMbbxData();
});