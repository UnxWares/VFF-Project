{
					__sveltekit_gi0htk = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795577287096",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_gi0htk);
						const app = await import("/docs/graphql/_app/immutable/entry/app.BoG2TLmt.js");
						kit.start(app, element, {
							node_ids: [0, 7],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
