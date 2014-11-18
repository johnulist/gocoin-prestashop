<div class="">
    {if $gocoin_validation}
        <div class="conf">
            {foreach from=$gocoin_validation item=validation}
                {$validation|escape:'htmlall':'UTF-8'}<br />
            {/foreach}
        </div>
    {/if}
    {if $gocoin_error}
        <div class="error">
            {foreach from=$gocoin_error item=error}
                {$error|escape:'htmlall':'UTF-8'}<br />
            {/foreach}
        </div>
    {/if}
    {if $gocoin_warning}
        <div class="info">
            {foreach from=$gocoin_warning item=warning}
                {$warning|escape:'htmlall':'UTF-8'}<br />
            {/foreach}
        </div>
    {/if}

    {if $php_version_allowed eq 'N'}
      <div class="error">
          <div style="color:#ff0000;font-weight: bold;"> The minimum PHP version required for GoCoin plugin is 5.3.0</div>
      </div>
    {else}
    <form action="" method="post" id="" class="half-form L">
        
        <fieldset>
            <legend><img src="{$module_dir}img/settings.gif" alt="" /><span>{l s='GoCoin API Settings' mod='gocoin'}</span></legend>
            <div id="">
                <label for="gocoin_merchant_id">{l s=' Merchant ID :' mod='gocoin'}<sup style="color: red;">*</sup></label></td>
                <div class="margin-form">
                    <input type="text" name="gocoin_merchant_id" id="gocoin_merchant_id" class="input-text" style="width:265px !important" value="{if $gocoin_configuration.GOCOIN_MERCHANT_ID}{$gocoin_configuration.GOCOIN_MERCHANT_ID|escape:'htmlall':'UTF-8'}{/if}" />  
                </div>
                <label for="gocoin_access_key">{l s='Api Key:' mod='gocoin'}<sup style="color: red;">*</sup></label></td>
                <div class="margin-form">
                    <input type="text" name="gocoin_access_key" id="gocoin_access_key" class="input-text" style="width:265px !important"  value="{if $gocoin_configuration.GOCOIN_ACCESS_KEY}{$gocoin_configuration.GOCOIN_ACCESS_KEY|escape:'htmlall':'UTF-8'}{/if}" />  
                </div>

            </div>

            <div class="margin-form">
                <input type="submit" name="SubmitBasicSettings" class="button" value="{l s='Save settings' mod='gocoin'}" />
            </div>
            <span class="small"><sup style="color: red;">*</sup> {l s='Required fields' mod='gocoin'}</span>
        </fieldset>
    </form>
    {/if}    
</div>
