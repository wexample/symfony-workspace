<script>
import ZoneTiling from '@wexample/symfony-design-system/components/zone-tiling/zone-tiling.vue';
import { uiStateGet, uiStateSet } from '@wexample/symfony-design-system/js/Helper/UiStateHelper';
import { filterTextMatches } from '@wexample/symfony-design-system/js/Helper/FilterHelper';

// What a zone's menu adds once it shows a widget.
export const WORKSPACE_ACTION_CHANGE = 'workspace:change';

// From how many widgets the choice of one is narrowed by typing.
const PICKER_FILTER_FROM = 8;

// How long the arrangement settles before it is kept: a seam dragged is one
// change, not one per pixel.
const SAVE_DELAY_MS = 600;

// The event a page sends the board to speak to its widgets, and the one it
// hears them on: `detail` is `{ type, detail }`.
export const WORKSPACE_EMIT_EVENT = 'workspace:emit';
export const WORKSPACE_EVENT = 'workspace:event';

const DOMAIN = 'WexampleSymfonyWorkspaceBundle.components.workspace-board.workspace-board::';

// A screen the reader composes: zones cut and sized as in a zone-tiling, each
// showing a widget chosen among those the page offers (WorkspaceWidgetInterface
// on the server, handed as `widgets`), the arrangement kept for the account —
// or in the visitor's own state without one.
//
// The widgets speak to each other through the board (`workspace.emit(type,
// detail)`, `workspace.on(type, listener)`), and to the rest of the page: each
// event is told on the board's element (`workspace:event`), and the page
// speaks to them by sending it `workspace:emit`. With `live`, what the server
// publishes about the subject reaches them as the `live` event.
//
// A zone — or a split, for a section — may be about a record of its own
// (`subject`); a widget is handed the nearest one, the board's at the top.
export default {
  template: '#vue-template-wexample-symfony-workspace-bundle-components-workspace-board-workspace-board',

  components: {
    ZoneTiling
  },

  props: {
    layoutKey: {
      type: String,
      required: true
    },
    // { entityName, id }: what the whole board is about.
    subject: {
      type: Object,
      default: null
    },
    // [{ name, label, description, icon, view }].
    widgets: {
      type: Array,
      default: () => []
    },
    // The arrangement kept, or the page's own; none, a single empty zone.
    layout: {
      type: Object,
      default: null
    },
    saveUrl: {
      type: String,
      default: null
    },
    saveToken: {
      type: String,
      default: null
    },
    flush: {
      type: Boolean,
      default: false
    },
    live: {
      type: Boolean,
      default: false
    },
    // `(zone, items, board) => items`: the page's own entries in a zone's menu.
    zoneMenu: {
      type: Function,
      default: null
    }
  },

  data() {
    return {
      tree: this.saveUrl ? this.layout : uiStateGet(this.app, this.stateKey(), this.layout),
      pickerFilter: ''
    };
  },

  computed: {
    widgetsByName() {
      return Object.fromEntries(this.widgets.map((widget) => [widget.name, widget]));
    },

    pickerFilterable() {
      return this.widgets.length >= PICKER_FILTER_FROM;
    },

    pickerWidgets() {
      return this.widgets.filter((widget) => filterTextMatches(this.pickerFilter, widget.label, widget.description));
    },

    // Each zone's subject, the nearest one above it holding.
    subjects() {
      const subjects = {};
      const walk = (node, inherited) => {
        const subject = node.subject ?? inherited;

        subjects[node.id] = subject;
        (node.children ?? []).forEach((child) => walk(child, subject));
      };

      if (this.tree) {
        walk(this.tree, this.subject);
      }

      return subjects;
    }
  },

  created() {
    this.listeners = new Map();
    this.definitions = {};
    this.saveTimer = null;
    // What a widget is handed to speak through: the board's words, not the
    // board itself.
    this.api = Object.freeze({
      layoutKey: this.layoutKey,
      emit: (type, detail) => this.emit(type, detail),
      on: (type, listener) => this.on(type, listener),
      updateConfig: (zoneId, config) => this.$refs.tiling?.update(zoneId, { config })
    });
  },

  mounted() {
    this.$el.addEventListener(WORKSPACE_EMIT_EVENT, this.onPageEmit);

    if (this.live && this.subject) {
      this.app.services.liveUpdates?.connectToEntity({
        entityName: this.subject.entityName,
        id: this.subject.id,
        owner: this,
        onMessage: (connection, payload) => this.emit('live', payload)
      });
    }
  },

  beforeUnmount() {
    this.$el.removeEventListener(WORKSPACE_EMIT_EVENT, this.onPageEmit);
    this.app.services.liveUpdates?.disconnectOwner(this);
    window.clearTimeout(this.saveTimer);
  },

  methods: {
    t(key, args) {
      return this.trans(`${DOMAIN}${key}`, args);
    },

    renderIcon(name) {
      return name ? this.app.getServiceOrFail('icon').icon(name) : '';
    },

    stateKey() {
      return ['workspace.layout', this.layoutKey, this.subject?.entityName, this.subject?.id]
        .filter((part) => part !== undefined && part !== null)
        .join('.');
    },

    // The vue of a widget, as the loader registered it: the page required it.
    widgetComponent(name) {
      const widget = this.widgetsByName[name];

      if (!widget) {
        return null;
      }

      return this.definitions[name] ??= this.app
        .getServiceOrFail('vue')
        .initComponent(widget.view, this.rootComponent);
    },

    pick(zone, widget) {
      this.pickerFilter = '';
      this.$refs.tiling.update(zone.id, { widget: widget.name, config: {} });
    },

    zoneMenuItems(zone, items) {
      const own = zone.widget
        ? [
          {
            value: WORKSPACE_ACTION_CHANGE,
            icon: 'ph:bold/swap',
            label: this.t('menu.change'),
            run: (target, tiling) => tiling.update(target.id, { widget: null, config: {} })
          },
          { separator: true }
        ]
        : [];
      const all = [...own, ...items];

      return this.zoneMenu ? this.zoneMenu(zone, all, this.api) : all;
    },

    onTreeChange(tree) {
      this.tree = tree;
      window.clearTimeout(this.saveTimer);
      this.saveTimer = window.setTimeout(() => this.save(), SAVE_DELAY_MS);
      this.emit('layout', tree);
    },

    // Kept for the account where there is one, in the visitor's state
    // otherwise. A failed save loses nothing on screen.
    async save() {
      if (!this.saveUrl) {
        uiStateSet(this.app, this.stateKey(), this.tree);

        return;
      }

      try {
        await fetch(this.saveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.saveToken ?? '' },
          body: JSON.stringify({
            layoutKey: this.layoutKey,
            subjectType: this.subject?.entityName ?? null,
            subjectId: this.subject?.id ?? null,
            tree: this.tree
          })
        });
      } catch {
        // Kept on screen; the next change tries again.
      }
    },

    // Told to the widgets listening, then to the page.
    emit(type, detail) {
      (this.listeners.get(type) ?? []).forEach((listener) => listener(detail));
      this.$el?.dispatchEvent(new CustomEvent(WORKSPACE_EVENT, {
        bubbles: true,
        detail: { layoutKey: this.layoutKey, type, detail }
      }));
    },

    // Returns what stops listening; a widget calls it as it goes.
    on(type, listener) {
      if (!this.listeners.has(type)) {
        this.listeners.set(type, new Set());
      }

      this.listeners.get(type).add(listener);

      return () => this.listeners.get(type)?.delete(listener);
    },

    onPageEmit(event) {
      if (event.detail?.type) {
        // Not told back to the page: it is the page speaking.
        (this.listeners.get(event.detail.type) ?? []).forEach((listener) => listener(event.detail.detail));
      }
    }
  }
};
</script>
