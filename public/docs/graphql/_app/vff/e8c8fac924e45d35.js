{
					__sveltekit_1czbrzw = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795440242632",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_1czbrzw);
						const app = await import("/docs/graphql/_app/immutable/entry/app.DTXeOBbE.js");
						kit.start(app, element, {
							node_ids: [0, 5],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
