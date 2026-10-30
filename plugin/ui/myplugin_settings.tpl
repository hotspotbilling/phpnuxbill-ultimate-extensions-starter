{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" action="{Text::url('plugin/myplugin/settings')}">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <div class="panel panel-primary panel-hovered mb20">
        <div class="panel-heading">{Lang::T('My Plugin')} - {Lang::T('Settings')}</div>
        <div class="panel-body">
            <div class="form-group">
                <label class="col-md-2 control-label">{Lang::T('Enabled')}</label>
                <div class="col-md-6">
                    <select name="myplugin_enabled" class="form-control">
                        <option value="yes" {if $_c['myplugin_enabled'] eq 'yes'}selected{/if}>{Lang::T('Yes')}</option>
                        <option value="no" {if $_c['myplugin_enabled'] neq 'yes'}selected{/if}>{Lang::T('No')}</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2 control-label">{Lang::T('Greeting')}</label>
                <div class="col-md-6">
                    <input type="text" name="myplugin_greeting" class="form-control" value="{$_c['myplugin_greeting']|default:''|escape}">
                    <small class="help-block">{Lang::T('Shown on the customer page.')}</small>
                </div>
            </div>
            <div class="form-group">
                <div class="col-lg-offset-2 col-lg-10">
                    <button class="btn btn-primary" type="submit">{Lang::T('Save Change')}</button>
                    <a href="{Text::url('plugin/myplugin/overview')}" class="btn btn-default">{Lang::T('Cancel')}</a>
                </div>
            </div>
        </div>
    </div>
</form>

{include file="sections/footer.tpl"}
