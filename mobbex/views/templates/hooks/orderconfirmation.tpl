<center>
    <table class="table-response">
        <tr align="center">
            <th colspan="2">
                <h1 class="md-h1">{l s='Payment Information' mod='mobbex'}</h1>
            </th>
        </tr>
        <tr align="left">
            <td>{l s='Transaccion Status' mod='mobbex'}</td>
            <td>{$status|escape:'htmlall':'UTF-8'}</td>
        </tr>
        <tr align="left" style="margin-bottom: 20px;">
            <td>{l s='Operation Total' mod='mobbex'}</td>
            <td>${$total|escape:'htmlall':'UTF-8'}</td>
        </tr>

        <tr align="center">
            <th colspan="2">
                <h2 class="md-h1">{l s='Transaction Details' mod='mobbex'}</h2>
            </th>
        </tr>

        {foreach from=$sources item=source key=key}
            
            {if $source['source_type'] eq "card"}

                <tr align="left" style="border-top: 1px solid rgb(68, 68, 68);">
                    <td>{l s='Card' mod='mobbex'}</td>
                    <td>{$source['source_name']|escape:'htmlall':'UTF-8'}</td>
                </tr>
                <tr align="left">
                    <td>{l s='Number' mod='mobbex'}</td>
                    <td>{$source['source_number']|escape:'htmlall':'UTF-8'}</td>
                </tr>

                    {if $source['installment_name']}
                        <tr align="left" style="margin-bottom: 10px;">
                            <td>{l s='Selected Plan' mod='mobbex'}</td>
                            <td>{$source['installment_name']|escape:'htmlall':'UTF-8'}</td>
                        </tr>
                    {/if}
            {/if}

            {if $source['source_type'] eq "cash"}
                
                <tr align="left">
                    <td>{l s='Payment Source' mod='mobbex'}</td>
                    <td>{$source['source_name']|escape:'htmlall':'UTF-8'}</td>
                </tr>

                <img src="{$source['source_url']}" style="width: 100%; max-width: 450px; padding: 10px; display: block;" />

                <tr align="center">
                    <th colspan="2">
                        <span>{$status_message|escape:'htmlall':'UTF-8'}</span>
                    </th>
                </tr>
            {/if}

        {/foreach}
        
    </table>
</center>