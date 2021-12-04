<div class="card mt-2 panel" id="mobbex-order-widget">

    <div class="card-header">
        <img class='mobbex-logo' src="https://res.mobbex.com/images/sources/mobbex.png" alt="mobbex logo">
        <h2 class="card-header-title">Mobbex</h2>
    </div>

    <div class="card-body">

        <table id="mobbex-widget-table">
            <tr>
                <th>
                    <h3>Información del pago</h3>
                </th>
            </tr>
            <tr>
                <td>ID Trasacción:</td>
                <td>{$data['payment_id']}</td>
            </tr>
            <tr class="mobbex-color-column">
                <td>Analisis de Riesgo:</td>
                <td>{$data['risk_analysis']}</td>
            </tr>
            <tr>
                <td>Moneda:</td>
                <td>{$data['currency']}</td>
            </tr>
            <tr class="mobbex-color-column">
                <td>Total:</td>
                <td><strong>${$data['total']}</strong></td>
            </tr>

            <tr>
                <th>
                    <h3>Método de Pago</h3>
                </th>
            </tr>


            {foreach from=$sources item=source}
                {if $source['source_type'] eq 'card'}

                    <tr>
                        <td>Tarjeta:</td>
                        <td>{$source['source_name']}</td>
                    </tr>
                    <tr class="mobbex-color-column">
                        <td>Numero:</td>
                        <td>{$source['source_number']}</td>
                    </tr>
                    <tr>
                        <td>Plan:</td>
                        <td>{$source['installment_name']}</td>
                    </tr>
                    <tr class="mobbex-color-column mobbex-end-table">
                        <td>Monto:</td>
                        <td><strong>${$source['total']}</strong></td>
                    </tr>

                {/if}
                {if $source['source_type'] eq 'cash'}
                    <tr>
                        <td>Medio de Pago:</td>
                        <td>
                            <img src="{$source['source_url']}" style="max-width: 30px; display: inline;" />
                            {$source['source_name']}
                        </td>
                    </tr>
                {/if}
            {/foreach}

            <tr>
                <th>
                    <h3>Entidad/es</h3>
                </th>
            </tr>

            {foreach from=$entities item=entity}

                <tr>
                    <td>Nombre:</td>
                    <td>{$entity['entity_name']}</td>
                </tr>
                <tr class="mobbex-color-column">
                    <td>UID:</td>
                    <td>{$entity['entity_uid']}</td>
                </tr>
                <tr class="mobbex-end-table">
                    <td>Coupon:</td>
                    <td><a href="{$entity['coupon']}">VER</a></td>
                </tr>

            {/foreach}

            {hook h="displayMobbexOrderWidget" id="$id"}

        </table>

    </div>

    {literal}
        <style>
            #mobbex-order-widget {
                border: 1px solid #6f00ff!important;
                background-color: #fff;
                border-radius: 5px;
                box-shadow: 0 0 4px 0 rgba(0, 0, 0, 0.06);
                max-width: 33.33333%;
            }
            #mobbex-order-widget img {
                width: 50px;
                margin-right: 10px;
            }
            #mobbex-order-widget .card-header {
                display: flex;
                align-items: center;
                line-height: 1.5rem;
                background-color: #fafbfc;
                border-bottom: 1px solid #dbe6e9;
                border-radius: 5px 5px 0 0;
                padding: 0%;
            }
            #mobbex-order-widget .card-header h2 {
                padding: 0;
                margin: 0!important;
            }
            #mobbex-order-widget .card-body {
                padding: .625rem;
            }
            .mobbex-color-column {
                background-color: #f8f8f8;
            }
            #mobbex-widget-table {
                margin-bottom: 20px;
            }
            #mobbex-widget-table th {
                    padding-top: 20px!important;
                    width: 100%;
            }
            #mobbex-widget-table .mobbex-end-table {
                border-bottom: 1px solid #6f00ff;
                margin-bottom: 20px;
            }
        </style>
    {/literal}

</div>