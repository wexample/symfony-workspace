import WorkspaceService from '../Services/WorkspaceService';
import WorkspaceChannel from '../Class/WorkspaceChannel';

// A vue taking part in a workspace: it reads the channel's selection in its
// template or its computed, and redraws when another region changes it.
const WorkspaceVueMixin = {
  methods: {
    workspaceChannel(name: string): WorkspaceChannel {
      return (this.app.getServiceOrFail(WorkspaceService) as WorkspaceService).channel(name);
    },
  },
};

export default WorkspaceVueMixin;
