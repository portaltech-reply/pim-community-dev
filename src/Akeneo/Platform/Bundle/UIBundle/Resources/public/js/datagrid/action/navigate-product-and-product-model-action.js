'use strict';

define(['underscore', 'oro/translator', 'oro/datagrid/navigate-action', 'pim/router'], function (
  _,
  __,
  NavigateAction,
  Router
) {
  return NavigateAction.extend({
    tabRedirects: {},
    links: {},

    /**
     * {@inheritdoc}
     */
    initialize() {
      return NavigateAction.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    getLink() {
      const productType = this.model.get('document_type');
      const id = this.model.get('technical_id');

      if (typeof this.links[productType] !== 'undefined') {
        return Router.generate(this.links[productType], {id});
      }

      return Router.generate('pim_enrich_' + productType + '_edit', {id});
    },

    /**
     * {@inheritdoc}
     */
    run() {
      if (null !== this.tabRedirects) {
        const productType = this.model.get('document_type');
        const tab = this.tabRedirects[productType];

        if (tab) {
          sessionStorage.setItem('redirectTab', `#${tab}`);
          sessionStorage.setItem('current_column_tab', tab);
        }
      }

      return NavigateAction.prototype.run.apply(this, arguments);
    },
  });
});
