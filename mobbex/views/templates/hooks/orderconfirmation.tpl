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

        {if $mobbex_data['source']['type'] eq "card"}
            <tr align="center">
                <th colspan="2">
                    <h2 class="md-h1">{l s='Card Details' mod='mobbex'}</h2>
                </th>
            </tr>
            <tr align="left">
                <td>{l s='Number' mod='mobbex'}</td>
                <td>{$mobbex_data['source']['number']|escape:'htmlall':'UTF-8'}</td>
            </tr>

                {if $mobbex_data['source']['installment']}
                    <tr align="left">
                        <td>{l s='Installments' mod='mobbex'}</td>
                        <td>{$mobbex_data['source']['installment']['description']|escape:'htmlall':'UTF-8'}</td>
                    </tr>
                {/if}
        {/if}
    </table>
    <p/>
</center>