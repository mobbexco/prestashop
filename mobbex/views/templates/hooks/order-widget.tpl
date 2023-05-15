<div class="card mt-2 panel" id="mobbex-order-widget">

    <div class="card-header">
        <img class='mobbex-logo' src="https://res.mobbex.com/images/sources/mobbex.png" alt="mobbex logo">
        <h2 class="card-header-title">Mobbex</h2>
    </div>

    <div class="card-body">

        <table id="mobbex-widget-table">
            <tr>
                <th>
                    <h3>{l s='Payment Information' mod='mobbex'}</h3>
                </th>
            </tr>
            <tr>
                <td>{l s='Transaction ID:' mod='mobbex'}</td>
                <td>{$data['payment_id']}</td>
            </tr>
            <tr class="mobbex-color-column">
                <td>{l s='Risk Analysis:' mod='mobbex'}</td>
                <td>{$data['risk_analysis']}</td>
            </tr>
            <tr>
                <td>{l s='currency' mod='mobbex'}</td>
                <td>{$data['currency']}</td>
            </tr>
            <tr class="mobbex-color-column">
                <td>Total:</td>
                <td><strong>${$data['total']}</strong></td>
            </tr>
			 <tr class="mobbex-color-column">
                <td>Status:</td>
                <td><strong>{$data['status_message']}</strong></td>
            </tr>

            <tr class="mobbex-end-table"></tr>
            
            <tr>
                <th>
                    <h3>{l s='Payment Method' mod='mobbex'}</h3>
                </th>
            </tr>


            {foreach from=$sources item=source}
                {if $source['source_type'] eq 'card'}

                    <tr>
                        <td>{l s='Card' mod='mobbex'}</td>
                        <td>{$source['source_name']}</td>
                    </tr>
                    <tr class="mobbex-color-column">
                        <td>{l s='Number:' mod='mobbex'}</td>
                        <td>{$source['source_number']}</td>
                    </tr>
                    <tr>
                        <td>{l s='Installment:' mod='mobbex'}</td>
                        <td>{$source['installment_name']}</td>
                    </tr>
                    <tr class="mobbex-color-column" >
                        <td>{l s='Amount:' mod='mobbex'}</td>
                        <td><strong>${$source['total']}</strong></td>
                    </tr>

                {/if}
                {if $source['source_type'] eq 'cash'}
                    <tr>
                        <td>{l s='Payment Source:' mod='mobbex'}</td>
                        <td>
                            <img src="{$source['source_url']}" style="max-width: 30px; display: inline;" />
                            {$source['source_name']}
                        </td>
                    </tr>
                {/if}
            {/foreach}

            <tr class="mobbex-end-table"></tr>

            <tr>
                <th>
                    <h3>{l s='Entities' mod='mobbex'}</h3>
                </th>
            </tr>

            {foreach from=$entities item=entity}

                <tr>
                    <td>{l s='Name:' mod='mobbex'}</td>
                    <td>{$entity['entity_name']}</td>
                </tr>
                <tr class="mobbex-color-column">
                    <td>UID:</td>
                    <td>{$entity['entity_uid']}</td>
                </tr>

            {/foreach}
            
            <tr class="mobbex-end-table">
                <td>Coupon:</td>
                <td><a href="{$coupon}">VER</a></td>
            </tr>

            {hook h="displayMobbexOrderWidget" id="$id" cart_id="$cart_id"}

        </table>
 
        {if $capture}
             <a id= "mobbex-capture-button-link" href="{$captureUrl}" ><button id="mobbex-capture-button">CAPTURE</button></a>
        {/if}
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

           #mobbex-capture-button-link {
                display: flex;
                flex-direction: column;
                text-decoration: none;
            }
            #mobbex-capture-button {
                display: flex;
                background-color: #6f00ff;
                border-color: #6100e0;
                text-decoration: inherit;
                border-radius: 5px;
                color: #f8f8f8;
                flex-direction: column;
                align-items: center;
                margin: 0 50px;
            }
            #mobbex-capture-button:hover {
                background-color: #6100e0;
                color:#f8f8f8;
            }

        </style>
    {/literal}

</div>