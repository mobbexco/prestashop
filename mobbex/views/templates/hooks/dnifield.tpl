{literal}
<script>
  var billingDni = document.querySelector('#billing_dni');

  document.forms['account-creation_form'].onsubmit = function () {
    if (billingDni.value == "") {
      return false;
    }
  }
</script>
{/literal}
<div class="required form-group">
  <label for="billing_dni">DNI <sup>*</sup></label>
  <input type="text" class="is_required validate form-control" data-validate="billing_dni" id="billing_dni" name="billing_dni" value="{$last_dni}">
</div>