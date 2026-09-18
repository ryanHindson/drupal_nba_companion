import re

from flask import Flask, jsonify, request
from nba_api.stats.static import teams
from nba_api.stats.endpoints import leaguedashteamstats
from nba_api.stats.static import players
from nba_api.stats.endpoints import playercareerstats
from nba_api.stats.endpoints import playerindex
from nba_api.stats.endpoints import leaguedashplayerstats

app = Flask(__name__)

@app.get("/health")

def health():
    return jsonify({"status": "healthy"})


@app.get("/nba/teams")
def get_teams():
    # This list is bundled with nba_api; it makes no network request.
    return jsonify({"data": teams.get_teams()})


@app.get("/nba/team-season-stats")
def get_team_season_stats():
    season = request.args.get("season", "2025-26")

    if not re.fullmatch(r"\d{4}-\d{2}", season):
        return jsonify({"error": "Use a season such as 2025-26"}), 400

    try:
        response = leaguedashteamstats.LeagueDashTeamStats(
            season=season,
            season_type_all_star="Regular Season",
            measure_type_detailed_defense="Base",
            per_mode_detailed="PerGame",
            timeout=30,
        )

        dataset = response.league_dash_team_stats.get_dict()
        records = [
            dict(zip(dataset["headers"], row))
            for row in dataset["data"]
        ]
        return jsonify({"season": season, "data": records})

    except Exception:
        app.logger.exception("NBA team stats request failed")
        return jsonify({"error": "NBA stats source is unavailable"}), 502
#@app.post("/stats")

@app.get("/nba/season-players")
def get_season_players():
    season = request.args.get("season", "2025-26").strip()

    if not re.fullmatch(r"\d{4}-\d{2}", season):
        return jsonify({
            "error": "Season must use the format YYYY-YY, such as 2025-26."
        }), 400

    try:
        endpoint = playerindex.PlayerIndex(
            season=season,
            league_id="00",
            timeout=60,
        )

        dataset = endpoint.player_index.get_dict()
        rows = [
            dict(zip(dataset["headers"], values))
            for values in dataset["data"]
        ]
    except Exception:
        app.logger.exception("Could not retrieve players for %s", season)
        return jsonify({
            "error": "Could not retrieve season players from NBA."
        }), 502

    players = []

    for row in rows:
        team_id = row.get("TEAM_ID")

        if team_id in (None, "", 0, "0"):
            continue

        jersey = row.get("JERSEY_NUMBER")

        players.append({
            "id": row["PERSON_ID"],
            "full_name": (
                f"{row.get('PLAYER_FIRST_NAME', '')} "
                f"{row.get('PLAYER_LAST_NAME', '')}"
            ).strip(),
            "jersey_number": (
                str(jersey)
                if jersey is not None and jersey != ""
                else None
            ),
            "position": row.get("POSITION") or None,
            "team_id": team_id,
            "team_name": row.get("TEAM_NAME") or None,
            "team_abbreviation": row.get("TEAM_ABBREVIATION") or None,
        })

    return jsonify({
        "season": season,
        "count": len(players),
        "data": players,
    })

@app.get("/nba/player-season-stats")
def get_player_season_stats():
    season = request.args.get("season", "2025-26").strip()
    season_type = request.args.get(
        "season_type", "Regular Season"
    ).strip()

    if not re.fullmatch(r"\d{4}-\d{2}", season):
        return jsonify({
            "error": "Season must use YYYY-YY, such as 2025-26."
        }), 400

    if season_type not in ("Regular Season", "Playoffs"):
        return jsonify({
            "error": "season_type must be Regular Season or Playoffs."
        }), 400

    try:
        endpoint = leaguedashplayerstats.LeagueDashPlayerStats(
            season=season,
            season_type_all_star=season_type,
            measure_type_detailed_defense="Base",
            per_mode_detailed="PerGame",
            league_id_nullable="00",
            rank="Y",
            timeout=60,
        )

        dataset = endpoint.league_dash_player_stats.get_dict()
        rows = [
            dict(zip(dataset["headers"], values))
            for values in dataset["data"]
        ]

        return jsonify({
            "season": season,
            "season_type": season_type,
            "count": len(rows),
            "data": rows,
        })

    except Exception:
        app.logger.exception(
            "Could not retrieve player stats for %s (%s)",
            season,
            season_type,
        )
        return jsonify({
            "error": "Could not retrieve player season stats from NBA."
        }), 502
    
if __name__ == "__main__":
    app.run(host="127.0.0.1", port=8000)