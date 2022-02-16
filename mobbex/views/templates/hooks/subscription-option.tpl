<div class="mbbx-subscription">
    <label for="sub_enable" style="margin-right: 20px;">Modo suscripción</label>
    <select id="sub_enable" name="sub_enable" onchange="document.querySelector('#subscription_uid').classList.toggle('mbbx-hidden')">
        <option value="no" {if $subscription['enable'] === 'no'}selected{/if}>Disable</option>
        <option value="yes" {if $subscription['enable'] === 'yes'}selected{/if}>Enable</option>
    </select>
    <div id='subscription_uid' class="sub_uid {if $subscription['enable'] === 'no'}mbbx-hidden{/if}">
        <label for="sub_uid">UID de la suscripción:</label>
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