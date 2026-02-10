const { Client } = require("@notionhq/client");

// ─── 설정 ───
const notion = new Client({ auth: process.env.NOTION_API_TOKEN });
const databaseId = process.env.NOTION_DATABASE_ID;

// ─── GitHub 환경변수에서 커밋 정보 추출 ───
function getCommitInfo() {
  const eventPath = process.env.GITHUB_EVENT_PATH;
  const event = require(eventPath);

  const commits = event.commits || [];

  return { commits };
}

// ─── Notion 데이터베이스에 커밋 추가 ───
async function addCommitToNotion(commit) {
  try {
    await notion.pages.create({
      parent: { database_id: databaseId },
      properties: {
        "Commit Message": {
          title: [
            {
              text: {
                content: commit.message.substring(0, 100),
              },
            },
          ],
        },
        "Author": {
          rich_text: [
            {
              text: {
                content: commit.author?.name || commit.author?.username || "unknown",
              },
            },
          ],
        },
        "Commit Hash": {
          rich_text: [
            {
              text: {
                content: commit.id.substring(0, 7),
              },
            },
          ],
        },
        "Commit URL": {
          url: commit.url,
        },
        "Date": {
          date: {
            start: commit.timestamp,
          },
        },
      },
    });

    console.log(`✅ 노션에 기록 완료: ${commit.message.substring(0, 50)}`);
  } catch (error) {
    console.error(`❌ 노션 기록 실패: ${error.message}`);
    console.error("상세 에러:", JSON.stringify(error.body || error, null, 2));
  }
}

// ─── 메인 실행 ───
async function main() {
  const { commits } = getCommitInfo();

  if (commits.length === 0) {
    console.log("📝 기록할 커밋이 없습니다.");
    return;
  }

  console.log(`📦 ${commits.length}개 커밋을 노션에 기록합니다...`);

  for (const commit of commits) {
    await addCommitToNotion(commit);
  }

  console.log("🎉 모든 커밋 기록 완료!");
}

main().catch((error) => {
  console.error("스크립트 실행 실패:", error);
  process.exit(1);
});
