class CitySpaces extends React.Component {
  checkHistInfo = event => {
    let contentDistricts = null;
    if (this.props.hist_name !== null) {
      contentDistricts = [
        {
          heading: "Historic Name",
          content: this.props.hist_name
        },
        {
          heading: "District",
          content: this.props.hist_place_name
        }
      ];
    } else {
      contentDistricts = [
        {
          heading: "Historic District",
          content: <div>This address is not in a historic district.</div>
        }
      ];
    }
    contentDistricts.push({
      content: (
        <div>
          Learn more about <a href={"/departments/landmarks-commission"} className="mnl-link">the City's historic districts</a>.
        </div>
      )
    }) 
    return contentDistricts;
  };
  render() {
    // Content for card
    const contentLibArray = [
      {
        heading: "Branch",
        content: <div>
                    <div>{this.props.library_branch}</div>
                    <div>{this.props.library_address}</div>
                    <div>Boston, MA {this.props.library_zipcode}</div>
                  </div> 
      },
      {
        content: (
          <div>
            Learn more about the City's <a href={"https://www.bpl.org/"} target="_blank" rel="noreferrer" className="mnl-link">library system</a>
          </div>
        )
      }
    ];
    const contentCommCenters = [
      {
        heading: "Location",
        content: <div>
                    <div>{this.props.comm_center}</div>
                    <div>{this.props.comm_address}</div>
                  </div>
      },
      {
        content: (
          <div>
            Learn more about the City's <a href={"/departments/boston-centers-youth-families"} className="mnl-link">community centers</a>
          </div>
        )
      }
    ];
    const contentPark = [
      {
        heading: "Park Name",
        content:  <div>
                    <div>{this.props.park_name}</div>
                    <div>{this.props.park_district}</div>
                  </div>
      },
      {
        heading: "Park Ownership",
        content: this.props.park_ownership
      },
      {
        heading: "Park Type",
        content: this.props.park_type
      },
      {
        content: (
          <div>
            Learn more about <a href={"/departments/parks-and-recreation"} className="mnl-link">parks in the city</a>.
          </div>
        )
      }
    ];
    const secDesc =
      "Libraries, community centers, historic districts, parks and playgrounds near you.";
    const cardsCitySpaces = (
      <div>
        <div className="sh">
          <h2 className="sh-title">City Spaces</h2>
        </div>
        <div className="supporting-text">
          <p>{secDesc}</p>
        </div>
        <div className="g">
          {/* Library Branch */}
          <MnlCard
            title={"A Library Branch Near You"}
            image_header={
              configProps.pathImage+"open_book.svg"
            }
            content_array={contentLibArray}
          />

          {/* Community Center */}
          <MnlCard
            title={"A Community Center Near You"}
            image_header={
              configProps.pathImage+"family_house.svg"
            }
            content_array={contentCommCenters}
          />

          {/* Park Info */}
          <MnlCard
            title={"A Park Near You"}
            image_header={
              configProps.pathImage+"trees.svg"
            }
            content_array={contentPark}
          />
          {/* Historical Info */}
          <MnlCard
            title={"Historic Districts"}
            image_header={
              configProps.pathImage+"location.svg"
            }
            content_array={this.checkHistInfo()}
          />
        </div>
        <button className="t--upper t--sans"
          onClick={() => {
            this.props.displaySection(null);
          }}
        >
          Back to results
        </button>
      </div>
    );
    let displayCitySpaces;
    if (this.props.section == "city-spaces") {
      history.pushState(null, null, configProps.path+'?p3');
      displayCitySpaces = cardsCitySpaces;
    } else if (this.props.section == null) {
      displayCitySpaces = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"City Spaces"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("city-spaces");
          }}
          onKeyUp={() => {
            this.props.displaySection("city-spaces",event);
          }}
        >
          <MnlSection
            title={"City Spaces"}
            image_header={
              configProps.pathImage+"playground.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayCitySpaces = null;
    }
    return displayCitySpaces;
  }
}
