{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{Text::url('paymentgateway/mygateway')}">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">My Gateway</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">API Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="mygateway_api_key"
                                value="{$_c['mygateway_api_key']|default:''|escape}" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Webhook Secret')}</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" name="mygateway_secret"
                                value="{$_c['mygateway_secret']|default:''|escape}" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Currency')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="mygateway_currency" maxlength="3"
                                value="{$_c['mygateway_currency']|default:'USD'|escape}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Webhook URL</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" value="{$webhook_url}" readonly onclick="this.select()">
                            <small class="help-block">{Lang::T('Paste this URL in the provider dashboard.')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">{Lang::T('Save Change')}</button>
                            <a href="{Text::url('paymentgateway')}" class="btn btn-default">{Lang::T('Cancel')}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{include file="sections/footer.tpl"}
