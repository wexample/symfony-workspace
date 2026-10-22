import WorkspaceService from '../Services/WorkspaceService';
import WorkspaceChannel, { WorkspaceChannelListener } from '../Class/WorkspaceChannel';

const listenersByInstance = new WeakMap<object, { [name: string]: WorkspaceChannelListener }>();

// A vue taking part in a workspace. What a channel holds is copied into the
// vue's own state as soon as it reads it, and kept in step until it goes: the
// vue redraws through its own reactivity, the channel staying a plain object
// any script can share.
const WorkspaceVueMixin = {
  data() {
    return {
      workspaceSelections: {} as { [name: string]: string[] },
    };
  },

  beforeUnmount() {
    const listeners = listenersByInstance.get(this) ?? {};

    Object.entries(listeners).forEach(([name, listener]) => this.workspaceChannel(name).forget(listener));
    listenersByInstance.delete(this);
  },

  methods: {
    workspaceChannel(name: string): WorkspaceChannel {
      return (this.app.getServiceOrFail(WorkspaceService) as WorkspaceService).channel(name);
    },

    // The ids chosen on a channel, to read in a template or a computed.
    workspaceSelection(name: string): string[] {
      const listeners = listenersByInstance.get(this) ?? {};

      if (!listeners[name]) {
        const channel = this.workspaceChannel(name);

        listeners[name] = (selection) => {
          this.workspaceSelections[name] = [...selection.ids];
        };
        listenersByInstance.set(this, listeners);
        channel.listen(listeners[name]);
        this.workspaceSelections[name] = [...channel.ids];
      }

      return this.workspaceSelections[name] ?? [];
    },
  },
};

export default WorkspaceVueMixin;
