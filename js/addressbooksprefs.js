/**
 * Provides the javascript for managing addressbooks.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2.1 (http://www.horde.org/licenses/lgpl21)
 */

var HordeAddressbooksPrefs = {

    // Variables set by other code: fields, nonetext

    updateSearchFields: function()
    {
        var tmp,
            sv = Array.from(document.getElementById('selected_sources').selectedOptions).map(option => option.value),
            sf = document.getElementById('search_fields_select');

        // Remove all child elements
        while (sf.firstChild) {
            sf.removeChild(sf.firstChild);
        }

        if (sv.length == 1) {
            tmp = this.fields.get(sv[0]);
            tmp.entries.forEach(function(o) {
                var opt = document.createElement('option');
                opt.text = o.label;
                opt.value = o.name;
                if (tmp.selected.includes(o.name)) {
                    opt.selected = true;
                }
                sf.appendChild(opt);
            });
        } else {
            var tmpOption = document.createElement('option');
            tmpOption.text = this.nonetext;
            tmpOption.value = '';
            tmpOption.disabled = true;
            sf.appendChild(tmpOption);
        }
    },

    changeSearchFields: function()
    {
        var tmp,
            out = new Map(),
            sv = Array.from(document.getElementById('selected_sources').selectedOptions).map(option => option.value);

        if (sv.length == 1) {
            tmp = this.fields.get(sv[0]);
            tmp.selected = Array.from(document.getElementById('search_fields_select').selectedOptions).map(option => option.value);
            this.fields.set(sv[0], tmp);

            this.fields.forEach(function(value, key) {
                out.set(key, value.selected);
            });

            document.getElementById('search_fields').value = JSON.stringify(Object.fromEntries(out));
        }
    },

    onDomLoad: function()
    {
        this.fields = new Map(Object.entries(this.fields));

        this.updateSearchFields();

        document.getElementById('search_fields_select').addEventListener('change', this.changeSearchFields.bind(this));
        document.getElementById('selected_sources').addEventListener('change', this.updateSearchFields.bind(this));
        document.getElementById('selected_sources').addEventListener('HordeSourceSelectPrefs:remove', this.updateSearchFields.bind(this));
    }

};

document.addEventListener('DOMContentLoaded', function() {
    HordeAddressbooksPrefs.onDomLoad.bind(HordeAddressbooksPrefs)();
});
