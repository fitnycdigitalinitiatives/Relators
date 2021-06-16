var valueTriggerIndex = 0;
$(".field-container").on("change", ".inputs > select", function() {
  var property_id = $(this).val();
  var current_option = $(this).find("option[value='" + property_id + "']");
  var current_value_inputs = $(this).parent();
  var value_index = $(this).attr("name").split(']').shift().split('[').pop()[0];
  if (current_option.data('term') == 'dcterms:contributor') {
    ++valueTriggerIndex;
    // create template for the first
    if (valueTriggerIndex == 1) {
      //create template
      $.getJSON(localRelatorsJsonURL, function(relatorsJSON) {
        relators_template(relatorsJSON);
        apply_relators_template(current_value_inputs, value_index);
      });
    } else {
      if ($("#values").data("template-relators")) {
        apply_relators_template(current_value_inputs, value_index);
      } else {
        $(document).on("o-module-relators:template", function() {
          apply_relators_template(current_value_inputs, value_index);
        });
      }
    }
  } else {
    $(this).parent().find(".relator-selector").remove();
  }
});

function relators_template(relatorsJSON) {
  var form_template = `
    <div class="relator-selector">
      <select name="value[__INDEX__][o-module-relators:batchValues]" multiple data-placeholder="Relator(s)" aria-label="Relator(s)">
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
  $("#values").data("template-relators", form_template.prop('outerHTML'));
  $(document).trigger("o-module-relators:template");
}


function apply_relators_template(current_value_inputs, value_index) {
  var relator_form = $("#values").data("template-relators");
  $(current_value_inputs).children("label").first().before(relator_form.replace("__INDEX__", value_index));
  var selectForm = $(current_value_inputs).find('.relator-selector select');
  // apply chosen js after value is set
  $(selectForm).chosen({
    width: "100%"
  });
}