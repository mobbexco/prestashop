<div class="mbbx-subscription">
    <label for="sub_enable" style="margin-right: 20px;">{l s='Subscription Mode' mod='mobbex'}</label>
    <select id="sub_enable" name="sub_enable" onchange="document.querySelector('#subscription_uid').classList.toggle('mbbx-hidden')">
        <option value="no" {if $subscription['enable'] === 'no'}selected{/if}>{l s='Disabled' mod='mobbex'}</option>
        <option value="yes" {if $subscription['enable'] === 'yes'}selected{/if}>{l s='Enabled' mod='mobbex'}</option>
    </select>
    <div id='subscription_uid' class="sub_uid {if $subscription['enable'] === 'no'}mbbx-hidden{/if}">
        <label for="sub_uid">{l s='Subscription UID:' mod='mobbex'}</label>
        <input type="text" name="sub_uid" id="sub_uid" value="{$subscription['uid']}">
    </div>
</div>

<style>
    .mbbx-hidden {
        display: none;
    }
    #subscription_uid {
        margin-top: 10px;
    }
    .mbbx-subs label {
        margin-right: 25px;
    }
</style>