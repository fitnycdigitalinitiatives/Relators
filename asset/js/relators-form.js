var valueTriggerIndex = 0;
$(document).on('o:prepare-value', function(event, type, value, valueObj, namePrefix) {
  // check if contributor and check if already has relator selector (because of resource type will duplicate)
  if (((value.data('data-term') == 'dcterms:contributor') || (value.data('term') == 'dcterms:contributor')) && (!$(value).find('.relator-selector').length)) {
    ++valueTriggerIndex;
    // create template for the first
    if (valueTriggerIndex == 1) {
      //create template
      $.getJSON(localRelatorsJsonURL, function(relatorsJSON) {
        relators_template(relatorsJSON);
        apply_relators_template(value, valueObj);
      });
    } else {
      if ($('.relator-selector.template').length) {
        apply_relators_template(value, valueObj);
      } else {
        $(document).on("o-module-relators:template", function() {
          apply_relators_template(value, valueObj);
        });
      }
    }
  }
});

function relators_template(relatorsJSON) {
  var form_template = `
    <div class="relator-selector template">
      <select data-value-key="o-module-relators:values" multiple data-placeholder="Relator(s)" aria-label="Relator(s)">
      </select>
    </div>
  `;
  form_template = $(form_template);
  $.each(relatorsJSON, function(index, relator) {
    if ("http://www.loc.gov/mads/rdf/v1#authoritativeLabel" in relator) {
      label = relator["http://www.loc.gov/mads/rdf/v1#authoritativeLabel"][0]["@value"];
      uri = relator["@id"];
      new_option = `
        <option value="` + uri + `">` + label + `</option>
      `;
      $(form_template).children('select').append(new_option);
    }
  });
  // sort the options
  var opts_list = form_template.children('select').find('option');
  opts_list.sort(function(a, b) {
    return $(a).text() > $(b).text() ? 1 : -1;
  });
  form_template.children('select').html('').append(opts_list);
  $(".template.value").last().after(form_template);
  $(document).trigger("o-module-relators:template");
}

function apply_relators_template(value, valueObj) {
  var relator_form = $('.relator-selector.template').last().clone(true);
  relator_form.removeClass('template');
  $(value).children('.input-body').append(relator_form);
  var selectForm = $(value).find('select');
  if (valueObj && (typeof valuesRelators != 'undefined')) {
    console.log(valueObj);
    var valueObjUri = (("@id" in valueObj) && (valueObj["type"] == "uri")) ? valueObj["@id"] : null;
    var valueObjValue = ("o:label" in valueObj) ? valueObj["o:label"] : null;
    if (!valueObjValue) {
      var valueObjValue = ("@value" in valueObj) ? valueObj["@value"] : null;
    }
    var valueObjResourceValueId = ("value_resource_id" in valueObj) ? valueObj["value_resource_id"] : null;
    $.each(valuesRelators, function(index, relators) {
      var realtorsResourceValueId = (relators["o-module-relators:valueResourceMatch"]) ? relators["o-module-relators:valueResourceMatch"]["o:id"] : null;
      if ((valueObjUri == relators["o-module-relators:uriMatch"]) && (valueObjValue == relators["o-module-relators:valueMatch"]) && (valueObjResourceValueId == realtorsResourceValueId)) {
        if ("o-module-relators:values" in relators) {
          $(selectForm).val(relators["o-module-relators:values"]);
        }
      }
    });
  }
  // apply chosen js after value is set
  $(selectForm).chosen({
    width: "100%"
  });
}