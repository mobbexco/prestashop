{literal}
<script>
  var customerDni = document.querySelector('#customer_dni');

  document.forms['account-creation_form'].onsubmit = function () {
    if (customerDni.value == "") {
      return false;
    }
  }
</script>
{/literal}
<div class="required form-group">
  <label for="customer_dni">DNI <sup>*</sup></label>
  <input type="text" class="is_required validate form-control" data-validate="customer_dni" id="customer_dni" name="customer_dni" value="{$last_dni}">
</div>