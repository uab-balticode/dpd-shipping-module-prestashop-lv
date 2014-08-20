/* 
 * 
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * or OpenGPL v3 license (GNU Public License V3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * or
 * http://www.gnu.org/licenses/gpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@balticode.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category   Balticode
 * @package    Balticode_Dpd
 * @copyright  Copyright (c) 2013 UAB BaltiCode (http://www.balticode.com/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU Public License V3.0
 * @author     Šarūnas Narkevičius
 * 

 */
/**
 * <p>Adds price-per-country DOM</p>
 * @param {object} $
 * @param {type} undefined
 * @returns {undefined}
 */
(function($, undefined ) {
    var dataKey = 'balticode_dpdee';
    $.balticode_dpdee = {
        conf: {
            form_field_name: false,
            form_field_id: false
        }
    };
    /**
     * 
     * @param {object} conf
     * @param {dom} template
     * @returns {function}
     */
    function initTemplate(conf, template) {

        return {
            add: function(data) {
                var d = new Date(),
                        id = '_' + d.getTime() + '_' + d.getMilliseconds();
                if (!data) {
                    data = {};
                }
                if (!data.id) {
                    data.id = id;
                }
                data.id = conf.form_field_name + '[' + data.id + ']';

                $('#grid_' + conf.form_field_id + ' table tr:last-child').loadTemplate(template, data, {before: true});
                
            },
            remove: function(dom) {
                $(dom).closest('tr').remove();
            }
        };
    }
    
    
    
    $.fn.balticode_dpdee = function(conf, initialData) {
        conf = $.extend(true, {}, $.balticode_dpdee.conf, conf),
                $that = $(this);
        
        $.addTemplateFormatter("nameFormatter", function(value, template) {
            return template.replace("#{_id}", value);
        });
        
        if (!$that.data(dataKey)) {
            $that.data(dataKey, initTemplate(conf, $that));
            if (initialData && typeof initialData == 'object') {
                for (var i in initialData) {
                    initialData[i].id = i;
                    $that.data(dataKey).add(initialData[i]);
                }
            }
        }
        return $that.data(dataKey);
    };

}( jQuery ));


(function($, undefined ) {
    var dataKey = 'balticode_dpdee_couriercall';
    $.balticode_dpdee_couriercall = {
        conf: {
            info_box_id: false,
            style_rules: false,
            after_create: false,
            after_show: false,
            after_end_result: false,
        }
    };

    function initTemplate(conf, dom) {
        var _infoBoxId = conf.info_box_id,
                _styleRules = conf.style_rules,
                _afterCreate = conf.after_create,
                _afterShow = conf.after_show,
                _afterEndResult = conf.after_end_result,
                _offset = $('#' + _infoBoxId).offset(), _orderId, _created = false,
                _infoBox;
        return {
            /**
             * <p>Creates or returns block instance.</p>
             * @returns {Element} created on-demand-courier-call-block
             */
            getInfoBox: function() {
                if (!_infoBox || $('#' + _infoBoxId + '_box').length == 0) {
                    _infoBox = $('<div>');
                    _infoBox.attr('id', _infoBoxId + '_box');
                    _infoBox.attr('class', 'button_box');
                    _infoBox.css({
                        position: 'absolute',
                        top: (_offset.top - 100) + 'px',
                        left: (_offset.left - 87) + 'px',
                        'z-index': 2000
                    });
                    if (_styleRules) {
                        _infoBox.css(_styleRules);
                    }
                    
                    _infoBox.appendTo($('body'));
//                    $('body').append(_infoBox);
                    
                    if (_afterCreate) {
                        _afterCreate(_infoBox);
                    }
                    _created = true;
                    $('html').click(function(event) {
                        if (_infoBox.is(':visible')) {
                            _infoBox.hide();
                        }
                        
                    });
                    _infoBox.click(function(event) {
                        event.stopPropagation();
                        
                    });
                    $('#' + _infoBoxId).click(function(event) {
                        event.stopPropagation();
                        
                    });
                }
                return _infoBox;
            },
            /**
             * <p>Updates infobox instance with supplied HTML contents</p>
             * <p>Executes check action on the massaction object for supplied orderId.</p>
             * <p>Updates number of parcels in accordance of selected orders under massActionObject.</p>
             * @param {string} htmlContents
             * @param {string|int} orderId
             * @param {varienGridMassaction} massActionObject
             * @returns {undefined}
             */
            update: function(htmlContents) {
                this.getInfoBox();
                if (htmlContents) {
                    _infoBox.replaceWith(htmlContents);
                };
            },
            /**
             * 
             * <p>Attempts to submit data from infoBox form fields to server.</p>
             * <p>If submit cannot be done, then it hides infoBox.</p>
             * <p>If infoBox is hidden, then it displays infoBox.</p>
             * @param {string} url not required, when provided, ajax request also is sent.
             * @returns {object|Boolean}
             */
            submit: function(url, successFunction) {
                var endResult = false;
                if (_infoBox) {
                    if (_infoBox.is(':visible')) {
                        endResult = _infoBox.find("input, text, textarea, select").serializeArray();
                        if (endResult === {} || endResult === []) {
                            endResult = false;
                        }
                        if (_afterEndResult) {
                            endResult = _afterEndResult(endResult);
                        }
                        if (endResult && url) {
                            $.ajax(url, {
                                'type': 'POST',
                                'data': endResult,
                                'dataType': 'json',
                                'error': function(erro) {
                                    alert('Request failed, check your error logs');
                                },
                                'success': function(data) {
                                    if (successFunction) {
                                        successFunction(data, _infoBox);
                                    }
                                }.bind(this),
                            });
                        }
                        if (!endResult && !_created) {
                            _infoBox.hide();
                        }
                        if (_created) {
                            _created = false;
                        }


                    } else {
                        //infobox not visible
                        _infoBox.show();
                        if (_afterShow) {
                            _afterShow(_infoBox);
                        }
                        
                    }
                    return endResult;
                }
            }
        }
    }
    
    $.fn.balticode_dpdee_couriercall = function(conf) {
        conf = $.extend(true, {}, $.balticode_dpdee_couriercall.conf, conf),
                $that = $(this);
        
        
        if (!$that.data(dataKey)) {
            $that.data(dataKey, initTemplate(conf, $that));
        }
        return $that.data(dataKey);
    };
    
}( jQuery ));


(function($, undefined ) {
    var dataKey = 'balticode_dpdee_courierbutton';
    $.balticode_dpdee_courierbutton = {
        conf: {
            url: false,
            onclick: false,
            title: "Kutsu DPD kuller",
            button: false,
            a_class: 'toolbar_btn',
            button_id: 'balticode_dpdee__button_courier',
            redirect_click: false
        }
    };
    
    function initTemplate(conf, dom) {
        var button = $(conf.button),
                hrefs = button.find('a'),
                balticode_dpdJsObject;
        hrefs.attr('onclick', 'return false;');
        hrefs.attr('title', conf.title);
        hrefs.attr('class', conf.a_class);
        button.find('div').html(conf.title);
        button.attr('id', conf.button_id);
        
        
        //dom needs to be added
        dom.append(button);
        if (conf.url && !conf.redirect_click) {
            balticode_dpdJsObject = $('body').balticode_dpdee_couriercall({
                info_box_id: button.attr('id'),
                style_rules: null,
                after_create: function(infoBox) {
                    $.ajax(conf.url, {
                        'type': 'POST',
                        'data': {},
                        'async': false,
                        'dataType': 'json',
                        'error': function(erro) {
                            alert('Request failed, check your error logs');
                        },
                        'success': function(data) {
                            var ul = $('<ul>');
                            ul.attr('class', 'messages');
                            if (data.messages) {
                                $.each(data.messages, function(i, message) {
                                    ul.append('<li class="success-msg">' + message + '</li>');
                                });
                            }
                            if (data.errors) {
                                $.each(data.errors, function(i, message) {
                                    ul.append('<li class="error-msg">' + message + '</li>');
                                });
                            }
                            if (data.errors || data.messages) {
                                infoBox.html(ul);
                            }
                            if (data.html) {

                                infoBox.html(data.html);
                            }
                        },
                    });

                },
                after_show: null,
                after_end_result: function(endResult) {
                    var resArr = {};
                    for (var i = 0; i < endResult.length; i++) {
                        resArr[endResult[i].name] = endResult[i].value;
                    }
                    console.log('after_end_result');
                    console.log(resArr);
                    if (resArr['Po_parcel_qty']== '0' & resArr['Po_pallet_qty'] == '0' & resArr['Po_remark'] == '') {
                        console.log('after_end_result falsw');
                        return false;
                    }
console.log('after_end_result true');
console.log('after_end_result');


                    return endResult;
                },
            });

        }



        dom.find('a').click(function(event) {
            var submitResult;
            console.log(this.className);
            if (balticode_dpdJsObject) {
                console.log('click3');
                balticode_dpdJsObject.update('');
                submitResult = balticode_dpdJsObject.submit(conf.url, function(data, infoBox) {
                    var ul = $('<ul>');
                    ul.attr('class', 'messages');
                    if (data.errors || data.messages) {
                        if (data.errors) {
                            $.each(data.errors, function(i, message) {
                                ul.append('<li class="error-msg">' + message + '</li>');
                            });
                        }
                        if (data.messages) {
                            $.each(data.messages, function(i, message) {
                                ul.append('<li class="success-msg">' + message + '</li>');
                            });
                        }
                        infoBox.html(ul);
                    } else {
                        if (data.html) {
                            balticode_dpdJsObject.update(data.html, 'dummy', null);
                        }

                    }

                });
            }
            console.log(conf);
            if (conf.redirect_click && this.className != "toolbar_btn balticode_dpdee_call_courier_button") {
                console.log('click');
                window.open("data:application/pdf;," + escape(conf.pdf)); 
            }
        });
        return balticode_dpdJsObject;

    }

    $.fn.balticode_dpdee_courierbutton = function(conf) {
        conf = $.extend(true, {}, $.balticode_dpdee_courierbutton.conf, conf),
                $that = $(this);
        
        
        if (!$that.data(dataKey)) {
            $that.data(dataKey, initTemplate(conf, $that));
        }
        return $that.data(dataKey);
    };

    
}( jQuery ));



