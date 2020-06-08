<center>
    <table class="table-response">
        <tr align="center">
            <th colspan="2">
                <h1 class="md-h1">{l s='Payment Information' mod='mobbex'}</h1>
            </th>
        </tr>
        <tr align="left">
            <td>{l s='Transaction State' mod='mobbex'}</td>
            <td>{$status|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr align="left">
            <td>{l s='Total Value' mod='mobbex'}</td>
            <td>${$total|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr align="left">
            <td>{l s='Method' mod='mobbex'}</td>
            <td>{$payment|escape:'htmlall':'UTF-8'}</td>
        </tr>

        {if $mobbex_data['payment']['source']['type'] eq "card"}
            <tr align="center">
                <th colspan="2">
                    <h2 class="md-h1">{l s='Card Details' mod='mobbex'}</h2>
                </th>
            </tr>
            <tr align="left">
                <td>{l s='Tarjeta' mod='mobbex'}</td>
                <td>{$mobbex_data['payment']['source']['name']|escape:'htmlall':'UTF-8'}</td>
            </tr>
            <tr align="left">
                <td>{l s='Number' mod='mobbex'}</td>
                <td>{$mobbex_data['payment']['source']['number']|escape:'htmlall':'UTF-8'}</td>
            </tr>

                {if $mobbex_data['payment']['source']['installment']}
                    <tr align="left">
                        <td>{l s='Installments' mod='mobbex'}</td>
                        <td>{$mobbex_data['payment']['source']['installment']['description']|escape:'htmlall':'UTF-8'}</td>
                    </tr>
                {/if}
        {/if}

        {if $mobbex_data['payment']['source']['type'] eq "cash"}
            <tr align="center">
                <th colspan="2">
                    <h2 class="md-h1">{l s='Detalles de la Transaccion' mod='mobbex'}</h2>
                </th>
            </tr>
            <tr align="left">
                <td>{l s='Medio de Pago' mod='mobbex'}</td>
                <td>{$mobbex_data['payment']['source']['name']|escape:'htmlall':'UTF-8'}</td>
            </tr>

            <img src="{$mobbex_data['payment']['source']['url']}" style="width: 100%; max-width: 450px; padding: 10px; display: block;" />

            <tr align="center">
                <th colspan="2">
                    <span>{$mobbex_data['payment']['status']['message']|escape:'htmlall':'UTF-8'}</span>
                </th>
            </tr>
        {/if}
    </table>
    <p/>
</center>