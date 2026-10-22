import AppService from '@wexample/symfony-loader/js/Class/AppService';
import EventsService from '@wexample/symfony-loader/js/Services/EventsService';
import WorkspaceChannel from '../Class/WorkspaceChannel';

/**
 * The channels the regions of the page share, by name. The page names them,
 * the way it names a remembered zone: two workspaces on one page are two
 * names, and a region joins one by asking for it. The first to ask opens it.
 */
export default class WorkspaceService extends AppService {
  public static serviceName: string = 'workspace';
  public static dependencies: typeof AppService[] = [EventsService];

  private channels: { [name: string]: WorkspaceChannel } = {};

  channel(name: string): WorkspaceChannel {
    return this.channels[name] ??= new WorkspaceChannel(name, this.app);
  }
}
